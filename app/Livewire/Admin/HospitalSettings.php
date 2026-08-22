<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Reglages de l'etablissement (addendum v2, point 6).
 *
 * Le nom affiche en haut a droite de chaque interface vit en base, pas dans le
 * code : le produit doit pouvoir servir ailleurs qu'a HFD sans redeploiement.
 */
class HospitalSettings extends Component
{
    public string $hospitalName = '';

    public function mount(): void
    {
        $this->hospitalName = hospital_name();
    }

    public function save(): void
    {
        $this->validate([
            'hospitalName' => ['required', 'string', 'max:255'],
        ], attributes: ['hospitalName' => "nom de l'etablissement"]);

        Setting::put(Setting::HOSPITAL_NAME, $this->hospitalName);

        session()->flash('admin.status', "Nom de l'etablissement mis a jour.");
    }

    public function render(): View
    {
        return view('livewire.admin.hospital-settings');
    }
}
