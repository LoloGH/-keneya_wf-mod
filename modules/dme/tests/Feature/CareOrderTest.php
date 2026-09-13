<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Keneya\Dme\Models\CareOrder;
use Keneya\Dme\Models\NursingNote;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Models\User;
use Keneya\Dme\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Tests\TestCase;

/**
 * Soins programmés : prescription, attribution et portée de garde.
 *
 * La règle métier vérifiée ici est celle qui protège le dossier : un soin
 * confié ne concerne que son destinataire, un soin ouvert ne sort pas du
 * service prescripteur, et rien de tout cela ne dépend de l'interface.
 */
class CareOrderTest extends TestCase
{
    use RefreshDatabase;

    private Service $cardiologie;

    private Service $pediatrie;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();

        // Codes distincts de ceux du ServiceSeeder, qui a déjà peuplé la table.
        $this->cardiologie = Service::create(['name' => 'Unité A', 'code' => 'TST-A', 'is_active' => true]);
        $this->pediatrie = Service::create(['name' => 'Unité B', 'code' => 'TST-B', 'is_active' => true]);
        $this->patient = Patient::factory()->create();
    }

    private function doctor(): User
    {
        return $this->userWithRole(Rbac::ROLE_DOCTOR, ['service_id' => $this->cardiologie->id]);
    }

    private function nurse(Service $service, bool $onDuty = true): User
    {
        return $this->userWithRole(Rbac::ROLE_NURSE, [
            'service_id' => $service->id,
            'is_on_duty' => $onDuty,
            'on_duty_since' => $onDuty ? now() : null,
        ]);
    }

    private function prescribe(User $doctor, array $overrides = []): CareOrder
    {
        $this->actingAs($doctor)
            ->post(route('dme.care-orders.store', $this->patient), array_merge([
                'title' => 'Pansement',
                'priority' => 'routine',
                'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
            ], $overrides))
            ->assertRedirect();

        return CareOrder::latest('id')->firstOrFail();
    }

    public function test_un_medecin_prescrit_un_soin_ouvert_a_la_garde_de_son_service(): void
    {
        $order = $this->prescribe($this->doctor());

        $this->assertSame('planned', $order->status);
        $this->assertNull($order->assigned_nurse_id);
        $this->assertSame($this->cardiologie->id, $order->service_id);
        $this->assertStringStartsWith('SOIN-', $order->reference);
    }

    public function test_un_infirmier_ne_peut_pas_prescrire(): void
    {
        $this->actingAs($this->nurse($this->cardiologie))
            ->post(route('dme.care-orders.store', $this->patient), [
                'title' => 'Pansement',
                'priority' => 'routine',
                'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->assertSame(0, CareOrder::count());
    }

    public function test_un_soin_ouvert_est_visible_par_la_garde_du_service_prescripteur(): void
    {
        $order = $this->prescribe($this->doctor());
        $nurse = $this->nurse($this->cardiologie);

        $this->assertTrue(
            CareOrder::visibleTo($nurse)->whereKey($order->id)->exists(),
            'La garde du service prescripteur doit voir le soin ouvert.',
        );
    }

    public function test_un_soin_ouvert_reste_invisible_a_un_autre_service(): void
    {
        $order = $this->prescribe($this->doctor());
        $outsider = $this->nurse($this->pediatrie);

        $this->assertFalse(
            CareOrder::visibleTo($outsider)->whereKey($order->id)->exists(),
            'Un soin ne doit pas franchir la frontière du service prescripteur.',
        );
        $this->assertFalse($outsider->can('view', $order));
    }

    public function test_un_soin_ouvert_disparait_de_la_liste_hors_garde(): void
    {
        $order = $this->prescribe($this->doctor());
        $offDuty = $this->nurse($this->cardiologie, onDuty: false);

        $this->assertFalse(CareOrder::visibleTo($offDuty)->whereKey($order->id)->exists());
        $this->assertFalse($offDuty->can('execute', $order));
    }

    public function test_un_soin_confie_suit_son_destinataire_meme_hors_garde(): void
    {
        $nurse = $this->nurse($this->cardiologie, onDuty: false);
        $order = $this->prescribe($this->doctor(), ['assigned_nurse_id' => $nurse->id]);

        $this->assertSame($nurse->id, $order->assigned_nurse_id);
        $this->assertTrue(CareOrder::visibleTo($nurse)->whereKey($order->id)->exists());
        $this->assertTrue($nurse->can('execute', $order));
    }

    public function test_un_soin_confie_echappe_aux_autres_soignants_du_service(): void
    {
        $titulaire = $this->nurse($this->cardiologie);
        $collegue = $this->nurse($this->cardiologie);
        $order = $this->prescribe($this->doctor(), ['assigned_nurse_id' => $titulaire->id]);

        $this->assertFalse(CareOrder::visibleTo($collegue)->whereKey($order->id)->exists());
        $this->assertFalse($collegue->can('execute', $order));
    }

    public function test_on_ne_peut_pas_confier_un_soin_a_un_soignant_d_un_autre_service(): void
    {
        $etranger = $this->nurse($this->pediatrie);

        $this->actingAs($this->doctor())
            ->post(route('dme.care-orders.store', $this->patient), [
                'title' => 'Pansement',
                'priority' => 'routine',
                'starts_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'assigned_nurse_id' => $etranger->id,
            ])
            ->assertSessionHasErrors('assigned_nurse_id');

        $this->assertSame(0, CareOrder::count());
    }

    public function test_la_realisation_ecrit_une_transmission_signee(): void
    {
        $nurse = $this->nurse($this->cardiologie);
        $order = $this->prescribe($this->doctor(), ['assigned_nurse_id' => $nurse->id]);

        $this->actingAs($nurse)
            ->patch(route('dme.care-orders.execute', $order), [
                'status' => 'completed',
                'outcome' => 'Plaie propre.',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame($nurse->id, $order->completed_by_id);
        $this->assertNotNull($order->completed_at);

        $note = NursingNote::where('patient_id', $this->patient->id)->latest('id')->first();
        $this->assertNotNull($note, 'La réalisation doit laisser une transmission.');
        $this->assertSame($nurse->id, $note->nurse_id);
        $this->assertSame($order->title, $note->title);
    }

    public function test_un_soignant_hors_perimetre_ne_peut_pas_realiser_un_soin(): void
    {
        $order = $this->prescribe($this->doctor());
        $etranger = $this->nurse($this->pediatrie);

        $this->actingAs($etranger)
            ->patch(route('dme.care-orders.execute', $order), ['status' => 'completed'])
            ->assertForbidden();

        $this->assertSame('planned', $order->fresh()->status);
    }

    public function test_un_soin_clos_ne_se_realise_ni_ne_se_reattribue(): void
    {
        $nurse = $this->nurse($this->cardiologie);
        $order = $this->prescribe($this->doctor(), ['assigned_nurse_id' => $nurse->id]);

        $this->actingAs($nurse)
            ->patch(route('dme.care-orders.execute', $order), ['status' => 'completed'])
            ->assertRedirect();

        $this->actingAs($nurse)
            ->patch(route('dme.care-orders.execute', $order->fresh()), ['status' => 'completed'])
            ->assertForbidden();

        $this->actingAs($this->doctor())
            ->patch(route('dme.care-orders.assign', $order->fresh()), ['assigned_nurse_id' => null])
            ->assertForbidden();
    }

    public function test_un_refus_exige_un_motif(): void
    {
        $nurse = $this->nurse($this->cardiologie);
        $order = $this->prescribe($this->doctor(), ['assigned_nurse_id' => $nurse->id]);

        $this->actingAs($nurse)
            ->patch(route('dme.care-orders.execute', $order), ['status' => 'refused'])
            ->assertSessionHasErrors('outcome');

        $this->assertSame('planned', $order->fresh()->status);
    }

    public function test_le_prescripteur_voit_toujours_son_soin(): void
    {
        $doctor = $this->doctor();
        $nurse = $this->nurse($this->cardiologie);
        $order = $this->prescribe($doctor, ['assigned_nurse_id' => $nurse->id]);

        $this->assertTrue(CareOrder::visibleTo($doctor)->whereKey($order->id)->exists());
        $this->assertTrue($doctor->can('cancel', $order));
    }

    public function test_un_compte_desactive_ne_porte_plus_de_garde(): void
    {
        $order = $this->prescribe($this->doctor());
        $nurse = $this->nurse($this->cardiologie);
        $nurse->forceFill(['is_active' => false])->save();

        $this->assertFalse($nurse->fresh()->isOnDuty());
        $this->assertFalse($nurse->fresh()->can('execute', $order));
    }
}
