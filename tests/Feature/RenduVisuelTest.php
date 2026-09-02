<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Capture le HTML rendu de quelques ecrans sur disque, pour controle visuel au
 * navigateur pendant la refonte. Ce n'est pas une assertion de mise en page :
 * aucune valeur de couleur ni de pixel n'est verifiee ici, seulement que la
 * page repond et qu'on peut aller la regarder.
 */
class RenduVisuelTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_les_ecrans_pour_controle_visuel(): void
    {
        $dossier = env('KENEYA_CAPTURE_DIR');

        if (! $dossier) {
            $this->markTestSkipped('KENEYA_CAPTURE_DIR non defini : capture desactivee.');
        }

        $this->seedRoles();

        Setting::put(Setting::HOSPITAL_NAME, 'Hopital Fousseyni Daou');
        Setting::put(Setting::HOSPITAL_ADDRESS, 'BP 98 - Kayes Plateaux');
        Setting::put(Setting::HOSPITAL_PHONE, '+223 21 52 12 32');
        Setting::put(Setting::HOSPITAL_EMAIL, 'hfd_kayes@yahoo.fr');
        Setting::put(Setting::HOSPITAL_WEBSITE, 'www.hopital-fousseyni-daou.ml');
        Setting::put(Setting::HOSPITAL_HOURS, 'Lun - Ven : 07h30 - 17h00 | Sam : 07h30 - 13h00');
        Setting::put(Setting::HOSPITAL_MOTTO, 'Notre mission, votre sante.');

        $admin = $this->makeAdmin();
        $admin->update(['name' => 'Administrateur']);

        $reponse = $this->actingAs($admin->refresh())->get('/admin');
        $reponse->assertOk();

        @mkdir($dossier, 0755, true);
        file_put_contents($dossier.'/admin.html', $reponse->getContent());

        $this->assertFileExists($dossier.'/admin.html');
    }
}
