<?php

namespace App\Livewire\Shared;

use App\Actions\StoreSignatureImage;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Carte de profil, partagee par les cinq interfaces (v3.2.3, point 3).
 *
 * Remplace l'icone de deconnexion isolee : elle etait la seule action du coin
 * superieur droit, ce qui n'y laissait aucune place pour changer son mot de
 * passe — la seule chose qu'un agent ait besoin de faire sur son propre compte.
 */
class ProfileCard extends Component
{
    use WithFileUploads;

    public bool $open = false;

    /**
     * Signature et tampon du praticien (v3.2.9, point 2).
     *
     * Ils vivent dans la carte de profil, a cote du changement de mot de
     * passe : ce sont les deux seules choses qu'un agent gere sur son propre
     * compte. Le tampon de l'etablissement, lui, n'est pas ici — il est
     * institutionnel et se regle depuis /admin.
     */
    public bool $editingSignature = false;

    public $signatureFile = null;

    public $stampFile = null;

    /** Le formulaire de mot de passe est replie tant qu'on ne le demande pas. */
    public bool $changingPassword = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            // Regles minimales de complexite : huit caracteres, lettres et
            // chiffres. Rien de plus severe — un mot de passe impossible a
            // retenir finit ecrit sur un papier colle a l'ecran.
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'current_password' => 'mot de passe actuel',
            'password' => 'nouveau mot de passe',
        ];
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if (! $this->open) {
            $this->closePasswordForm();
        }
    }

    public function close(): void
    {
        $this->open = false;
        $this->closePasswordForm();
    }

    public function startSignatureChange(): void
    {
        $this->editingSignature = true;
        $this->reset(['signatureFile', 'stampFile']);
        $this->resetValidation();
    }

    public function closeSignatureForm(): void
    {
        $this->editingSignature = false;
        $this->reset(['signatureFile', 'stampFile']);
        $this->resetValidation();
    }

    /**
     * Depose l'une des deux images, ou les deux. Les regles de validation
     * doublent celles de l'action : le formulaire refuse tot, l'action refuse
     * quoi qu'il arrive.
     */
    public function saveSignature(StoreSignatureImage $action): void
    {
        $medecin = Auth::user()?->doctors()->orderBy('id')->first();

        if (! $medecin) {
            $this->addError('signatureFile', 'Seul un medecin dispose d\'une signature et d\'un tampon.');

            return;
        }

        $this->validate([
            'signatureFile' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'stampFile' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], attributes: ['signatureFile' => 'signature', 'stampFile' => 'tampon']);

        if (! $this->signatureFile && ! $this->stampFile) {
            $this->addError('signatureFile', 'Choisissez au moins une image a deposer.');

            return;
        }

        try {
            if ($this->signatureFile) {
                $action->forDoctorSignature($this->signatureFile, $medecin);
            }

            if ($this->stampFile) {
                $action->forDoctorStamp($this->stampFile, $medecin);
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('signatureFile', $e->getMessage());

            return;
        }

        session()->flash('profil.status', 'Signature et tampon mis a jour.');
        $this->closeSignatureForm();
    }

    public function startPasswordChange(): void
    {
        $this->changingPassword = true;
        $this->resetValidation();
    }

    public function closePasswordForm(): void
    {
        $this->reset(['changingPassword', 'current_password', 'password', 'password_confirmation']);
        $this->resetValidation();
    }

    public function changePassword(): void
    {
        $data = $this->validate();
        $user = Auth::user();

        // Verification cote serveur : le champ « mot de passe actuel » ne sert
        // a rien s'il n'est pas confronte au hash.
        if (! Hash::check($data['current_password'], $user->password)) {
            $this->addError('current_password', 'Le mot de passe actuel est incorrect.');

            return;
        }

        $user->forceFill(['password' => $data['password']])->save();

        // Un mot de passe change est souvent un mot de passe compromis : les
        // sessions ouvertes ailleurs tombent, la session courante survit.
        Auth::logoutOtherDevices($data['password']);

        Audit::log(
            Audit::EVENT_PASSWORD_CHANGED,
            sprintf('%s a change son mot de passe. Les autres sessions ont ete fermees.', $user->name),
            $user,
        );

        session()->flash('profil.status', 'Mot de passe modifie. Les autres sessions ont ete fermees.');

        $this->dispatch('message-affiche', level: 'success', message: 'Mot de passe modifie. Les autres sessions ont ete fermees.');

        $this->closePasswordForm();
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.shared.profile-card', [
            'user' => $user,
            'roleLabel' => $user?->roleLabel(),
            // Un medecin exerce dans un service, une receptionniste non : la
            // ligne disparait plutot que d'afficher un tiret.
            'serviceLabel' => $user?->doctors()->with('service')->first()?->service?->name
                ?? $user?->staffMember?->service?->name,
            'initials' => $this->initials($user?->name ?? ''),
            // Seul un praticien depose une signature et un tampon : la section
            // n'apparait pas pour une receptionniste ou un caissier.
            'isDoctor' => (bool) $user?->doctors()->exists(),
        ]);
    }

    /** Deux lettres au plus : un avatar generique sans photo a stocker. */
    private function initials(string $name): string
    {
        $mots = preg_split('/\s+/', trim($name)) ?: [];

        $lettres = collect($mots)
            ->filter()
            ->map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
            ->take(2)
            ->implode('');

        return $lettres !== '' ? $lettres : '?';
    }
}
