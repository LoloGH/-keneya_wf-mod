<?php

namespace Tests\Feature;

use App\Actions\SendReferral;
use App\Livewire\Admin\BillableItemManager;
use App\Livewire\Caisse\CaisseQueue;
use App\Livewire\Service\ServiceQueue;
use App\Models\BillableItem;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServiceKind;
use App\Models\Setting;
use App\Models\Visit;
use App\Services\SmsGateway;
use App\Services\SmsSendResult;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Catalogue de tarifs et objet du paiement (v3.2.8, point 3).
 *
 * Le montant se saisissait librement a la caisse : rien ne disait ce que le
 * patient payait, ni ne garantissait que deux caissiers demandent la meme somme
 * pour le meme acte.
 */
class BillableItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('deliver')->andReturn(SmsSendResult::sent());
    }

    // --------------------------------------------------- Administration

    public function test_l_admin_cree_modifie_et_supprime_un_tarif(): void
    {
        $radiologie = Service::factory()->create(['name' => 'Radiologie']);
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(BillableItemManager::class)
            ->set('name', 'Echographie abdominale')
            ->set('service_id', $radiologie->getKey())
            ->set('price', 7500)
            ->call('save')
            ->assertHasNoErrors();

        $acte = BillableItem::sole();
        $this->assertSame(7500, $acte->price);
        $this->assertSame('7 500 FCFA', $acte->formattedPrice());

        Livewire::actingAs($admin)
            ->test(BillableItemManager::class)
            ->call('edit', $acte->getKey())
            ->assertSet('price', 7500)
            ->set('price', 8000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8000, $acte->fresh()->price);

        Livewire::actingAs($admin)
            ->test(BillableItemManager::class)
            ->call('delete', $acte->getKey());

        $this->assertSame(0, BillableItem::count());
    }

    /**
     * Un tarif deja facture ne se supprime pas : le recu d'hier doit continuer
     * a dire ce qu'il disait.
     */
    public function test_un_tarif_deja_utilise_n_est_pas_supprimable(): void
    {
        $acte = BillableItem::factory()->create(['name' => 'Echographie abdominale']);

        Payment::factory()->create(['billable_item_id' => $acte->getKey()]);

        Livewire::actingAs($this->makeAdmin())
            ->test(BillableItemManager::class)
            ->call('delete', $acte->getKey());

        $this->assertSame(1, BillableItem::count());
    }

    // --------------------------------------------------- L'acte voyage

    /**
     * La verification demandee avant livraison : un renvoi vers un acte payant
     * affiche le bon tarif cote caisse, sans saisie manuelle.
     */
    public function test_un_renvoi_vers_un_acte_payant_prerempli_le_tarif_a_la_caisse(): void
    {
        [, $caisseServices] = $this->makeCaisses();

        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $radiologie = Service::factory()
            ->ofKind($this->serviceKind(ServiceKind::SLUG_PLATEAU_TECHNIQUE))
            ->create(['name' => 'Radiologie']);

        $acte = BillableItem::factory()->create([
            'name' => 'Echographie abdominale',
            'service_id' => $radiologie->getKey(),
            'price' => 7500,
        ]);

        $visit = $this->makeVisit($medecine, ['status' => Visit::STATUS_CALLED]);

        app(SendReferral::class)->execute(
            $visit,
            $this->makeDoctor($medecine),
            $radiologie,
            'Echographie a realiser.',
            $acte,
        );

        // L'acte est bien parti avec le renvoi, et la visite attend a la caisse.
        $this->assertSame($acte->getKey(), Referral::sole()->billable_item_id);
        $this->assertSame($caisseServices->getKey(), $visit->fresh()->service_id);

        // Le caissier ouvre l'encaissement : le montant est deja la, et il lit
        // le nom de l'acte au lieu de le demander.
        Livewire::actingAs($this->makeCashier())
            ->test(CaisseQueue::class, ['serviceId' => $caisseServices->getKey()])
            ->assertSee('Echographie abdominale')
            ->call('startPayment', $visit->getKey())
            ->assertSet('amount', 7500)
            ->assertSet('catalogPrice', 7500)
            ->assertSet('billableItemId', $acte->getKey());
    }

    /** Le medecin choisit l'acte precis, pas seulement le service. */
    public function test_le_medecin_choisit_l_acte_precis_au_renvoi(): void
    {
        $this->makeCaisses();

        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $radiologie = Service::factory()
            ->ofKind($this->serviceKind(ServiceKind::SLUG_PLATEAU_TECHNIQUE))
            ->create(['name' => 'Radiologie']);

        $acte = BillableItem::factory()->create([
            'name' => 'Echographie abdominale',
            'service_id' => $radiologie->getKey(),
            'price' => 7500,
        ]);

        // Un acte d'un autre service ne doit pas etre propose ici.
        BillableItem::factory()->create([
            'name' => 'Analyse, glycemie',
            'service_id' => Service::factory()->create(['name' => 'Laboratoire'])->getKey(),
        ]);

        $medecin = $this->makeDoctor($medecine);
        $visit = $this->makeVisit($medecine, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ServiceQueue::class, ['serviceId' => $medecine->getKey()])
            ->call('startReferral', $visit->getKey())
            ->set('toServiceId', $radiologie->getKey())
            ->assertSee('Echographie abdominale')
            ->assertDontSee('Analyse, glycemie')
            ->set('billableItemId', $acte->getKey())
            ->set('instructions', 'Echographie a realiser.')
            ->call('sendReferral')
            ->assertHasNoErrors();

        $this->assertSame($acte->getKey(), Referral::sole()->billable_item_id);
    }

    /** Un acte d'un autre service serait facture au mauvais tarif. */
    public function test_un_acte_etranger_au_service_destinataire_est_refuse(): void
    {
        $this->makeCaisses();

        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);
        $radiologie = Service::factory()
            ->ofKind($this->serviceKind(ServiceKind::SLUG_PLATEAU_TECHNIQUE))
            ->create(['name' => 'Radiologie']);
        $laboratoire = Service::factory()->create(['name' => 'Laboratoire']);

        $acteDuLabo = BillableItem::factory()->create(['service_id' => $laboratoire->getKey()]);

        $visit = $this->makeVisit($medecine, ['status' => Visit::STATUS_CALLED]);

        $this->expectException(InvalidArgumentException::class);

        app(SendReferral::class)->execute(
            $visit,
            $this->makeDoctor($medecine),
            $radiologie,
            'Echographie.',
            $acteDuLabo,
        );
    }

    // --------------------------------------------------- Ticket et derogation

    /** Le ticket de consultation s'applique tout seul a la caisse ticket. */
    public function test_le_ticket_de_consultation_est_applique_automatiquement(): void
    {
        [$ticket] = $this->makeCaisses();
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);

        $acte = BillableItem::factory()->create([
            'name' => 'Ticket de consultation',
            'service_id' => null,
            'price' => 1000,
        ]);

        Setting::put(Setting::TICKET_BILLABLE_ITEM_ID, (string) $acte->getKey());

        $visit = $this->makeVisit($ticket, [
            'pending_next_service_id' => $medecine->getKey(),
            'status' => Visit::STATUS_WAITING,
        ]);

        Livewire::actingAs($this->makeCashier())
            ->test(CaisseQueue::class, ['serviceId' => $ticket->getKey()])
            ->call('startPayment', $visit->getKey())
            ->assertSet('amount', 1000)
            ->assertSet('billableItemId', $acte->getKey());
    }

    /**
     * Une derogation reste possible, un caissier a parfois de bonnes raisons,
     * mais elle doit etre dite, et elle part au journal.
     */
    public function test_une_derogation_au_tarif_exige_un_motif_et_est_journalisee(): void
    {
        [$ticket] = $this->makeCaisses();
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);

        $acte = BillableItem::factory()->create(['name' => 'Ticket de consultation', 'price' => 1000]);
        Setting::put(Setting::TICKET_BILLABLE_ITEM_ID, (string) $acte->getKey());

        $visit = $this->makeVisit($ticket, [
            'pending_next_service_id' => $medecine->getKey(),
            'status' => Visit::STATUS_WAITING,
        ]);

        $caissier = $this->makeCashier();

        // Sans motif, l'encaissement a un autre montant est refuse.
        Livewire::actingAs($caissier)
            ->test(CaisseQueue::class, ['serviceId' => $ticket->getKey()])
            ->call('startPayment', $visit->getKey())
            ->set('overridePrice', true)
            ->set('amount', 500)
            ->call('confirmAndRoute')
            ->assertHasErrors('overrideReason');

        $this->assertSame(0, Payment::count());

        // Avec motif, il passe, et laisse une trace.
        Livewire::actingAs($caissier)
            ->test(CaisseQueue::class, ['serviceId' => $ticket->getKey()])
            ->call('startPayment', $visit->getKey())
            ->set('overridePrice', true)
            ->set('amount', 500)
            ->set('overrideReason', 'Patient indigent, accord de la direction.')
            ->call('confirmAndRoute')
            ->assertHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame(500, $payment->amount);
        $this->assertSame(1000, $payment->catalog_price);
        $this->assertTrue($payment->isOverridden());

        $trace = Activity::where('event', Audit::EVENT_PRICE_OVERRIDDEN)->sole();
        $this->assertStringContainsString('Patient indigent', $trace->description);
    }

    /** Encaisser au tarif ne demande rien de plus qu'avant. */
    public function test_encaisser_au_tarif_ne_demande_aucun_motif(): void
    {
        [$ticket] = $this->makeCaisses();
        $medecine = Service::factory()->create(['name' => 'Medecine Generale']);

        $acte = BillableItem::factory()->create(['name' => 'Ticket de consultation', 'price' => 1000]);
        Setting::put(Setting::TICKET_BILLABLE_ITEM_ID, (string) $acte->getKey());

        $visit = $this->makeVisit($ticket, [
            'pending_next_service_id' => $medecine->getKey(),
            'status' => Visit::STATUS_WAITING,
        ]);

        Livewire::actingAs($this->makeCashier())
            ->test(CaisseQueue::class, ['serviceId' => $ticket->getKey()])
            ->call('startPayment', $visit->getKey())
            ->call('confirmAndRoute')
            ->assertHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame(1000, $payment->amount);
        $this->assertSame($acte->getKey(), $payment->billable_item_id);
        $this->assertFalse($payment->isOverridden());
        $this->assertSame(0, Activity::where('event', Audit::EVENT_PRICE_OVERRIDDEN)->count());
    }

    // --------------------------------------------------- Recu et navigation

    /** Le recu doit dire ce qui a ete paye, pas seulement combien. */
    public function test_le_recu_imprime_nomme_l_acte_facture_et_son_tarif(): void
    {
        $acte = BillableItem::factory()->create(['name' => 'Echographie abdominale', 'price' => 7500]);

        $payment = Payment::factory()->create([
            'billable_item_id' => $acte->getKey(),
            'amount' => 7500,
            'catalog_price' => 7500,
        ]);

        $this->actingAs($this->makeCashier())
            ->get(route('caisse.receipt', $payment))
            ->assertOk()
            ->assertSee('Echographie abdominale')
            ->assertSee('7 500 FCFA');
    }

    /** « Caisse Ticket » avant « Caisse Services » : l'ordre du parcours. */
    public function test_la_navigation_de_la_caisse_place_le_ticket_avant_les_services(): void
    {
        $this->makeCaisses();

        $reponse = $this->actingAs($this->makeCashier())->get('/caisse')->assertOk();

        $contenu = $reponse->getContent();

        $this->assertLessThan(
            strpos($contenu, Service::CAISSE_SERVICES),
            strpos($contenu, Service::CAISSE_TICKET),
            'Caisse Ticket doit apparaitre avant Caisse Services dans la navigation.',
        );
    }
}
