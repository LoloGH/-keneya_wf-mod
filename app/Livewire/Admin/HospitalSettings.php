<?php

namespace App\Livewire\Admin;

use App\Actions\StoreSignatureImage;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\Doctor;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Reglages de l'etablissement (addendum v2, point 6 ; etendu en v3.2.9).
 *
 * Le nom affiche en haut a droite de chaque interface vit en base, pas dans le
 * code : le produit doit pouvoir servir ailleurs qu'a HFD sans redeploiement.
 * Les coordonnees de l'en-tete d'ordonnance suivent la meme regle, et le
 * tampon institutionnel se gere ici et nulle part ailleurs — un medecin ne
 * doit pas pouvoir remplacer le cachet de l'hopital depuis son profil.
 */
class HospitalSettings extends Component
{
    use NotifiesUser;
    use WithFileUploads;

    public string $hospitalName = '';

    public string $hospitalAddress = '';

    public string $hospitalPhone = '';

    public string $hospitalEmail = '';

    public string $hospitalWebsite = '';

    public string $hospitalHours = '';

    public string $hospitalMotto = '';

    public $stampFile = null;

    public function mount(): void
    {
        $this->hospitalName = hospital_name();
        $this->hospitalAddress = (string) Setting::get(Setting::HOSPITAL_ADDRESS, '');
        $this->hospitalPhone = (string) Setting::get(Setting::HOSPITAL_PHONE, '');
        $this->hospitalEmail = (string) Setting::get(Setting::HOSPITAL_EMAIL, '');
        $this->hospitalWebsite = (string) Setting::get(Setting::HOSPITAL_WEBSITE, '');
        $this->hospitalHours = (string) Setting::get(Setting::HOSPITAL_HOURS, '');
        $this->hospitalMotto = (string) Setting::get(Setting::HOSPITAL_MOTTO, '');
    }

    public function save(): void
    {
        $this->validate([
            'hospitalName' => ['required', 'string', 'max:255'],
            // Facultatives : un etablissement peut n'avoir pas encore de
            // courriel, et une ordonnance doit s'imprimer sans.
            'hospitalAddress' => ['nullable', 'string', 'max:255'],
            'hospitalPhone' => ['nullable', 'string', 'max:60'],
            'hospitalEmail' => ['nullable', 'email', 'max:255'],
            // Le site est saisi tel qu'il se lit sur une ordonnance
            // (« www.hopital-fousseyni-daou.ml ») : exiger un schema http
            // ferait echouer la saisie la plus naturelle.
            'hospitalWebsite' => ['nullable', 'string', 'max:255'],
            'hospitalHours' => ['nullable', 'string', 'max:255'],
            'hospitalMotto' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'hospitalName' => "nom de l'etablissement",
            'hospitalAddress' => 'adresse',
            'hospitalPhone' => 'telephone',
            'hospitalEmail' => 'courriel',
            'hospitalWebsite' => 'site web',
            'hospitalHours' => "horaires d'ouverture",
            'hospitalMotto' => 'devise',
        ]);

        Setting::put(Setting::HOSPITAL_NAME, $this->hospitalName);
        Setting::put(Setting::HOSPITAL_ADDRESS, $this->hospitalAddress ?: null);
        Setting::put(Setting::HOSPITAL_PHONE, $this->hospitalPhone ?: null);
        Setting::put(Setting::HOSPITAL_EMAIL, $this->hospitalEmail ?: null);
        Setting::put(Setting::HOSPITAL_WEBSITE, $this->hospitalWebsite ?: null);
        Setting::put(Setting::HOSPITAL_HOURS, $this->hospitalHours ?: null);
        Setting::put(Setting::HOSPITAL_MOTTO, $this->hospitalMotto ?: null);

        $this->notifySuccess("Coordonnees de l'etablissement mises a jour.");
    }

    public function saveStamp(StoreSignatureImage $action): void
    {
        $this->validate(
            ['stampFile' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']],
            attributes: ['stampFile' => "tampon de l'etablissement"],
        );

        try {
            $action->forHospitalStamp($this->stampFile);
        } catch (InvalidArgumentException $e) {
            $this->addError('stampFile', $e->getMessage());

            return;
        }

        $this->reset('stampFile');
        $this->notifySuccess("Tampon de l'etablissement mis a jour.");
    }

    public function render(): View
    {
        return view('livewire.admin.hospital-settings', [
            // Present seulement si le fichier est reellement la : un chemin
            // mort ne doit pas afficher une image cassee.
            'stampPresent' => Doctor::fichierExistant(Setting::get(Setting::HOSPITAL_STAMP_PATH)) !== null,
        ]);
    }
}
