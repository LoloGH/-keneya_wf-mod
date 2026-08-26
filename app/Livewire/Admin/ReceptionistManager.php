<?php

namespace App\Livewire\Admin;

use App\Actions\DeleteStaffAccount;
use App\Livewire\Concerns\NotifiesAdmin;
use App\Models\Receptionist;
use App\Models\User;
use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Gestion des receptionnistes : creation et mise a jour de leur compte.
 */
class ReceptionistManager extends Component
{
    use NotifiesAdmin;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $userId = $this->editingId
            ? Receptionist::find($this->editingId)?->user_id
            : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom de la receptionniste',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ];
    }

    public function edit(int $receptionistId): void
    {
        $receptionist = Receptionist::with('user')->findOrFail($receptionistId);

        $this->editingId = $receptionist->getKey();
        $this->name = $receptionist->user->name;
        $this->email = $receptionist->user->email;
        $this->password = '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            if ($this->editingId) {
                $receptionist = Receptionist::with('user')->findOrFail($this->editingId);

                $receptionist->user->update(array_filter([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'] ?: null,
                ], fn ($value) => $value !== null));

                return;
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->syncRoles([Roles::RECEPTIONIST]);

            $receptionist = Receptionist::create(['user_id' => $user->getKey()]);

            Audit::log(Audit::EVENT_RECEPTIONIST_CREATED, sprintf('Receptionniste %s creee.', $user->name), $receptionist);
        });

        $this->notifySuccess($this->editingId ? 'Receptionniste mise a jour.' : 'Receptionniste creee.');

        $this->cancel();
    }

    /**
     * Suppression du rattachement, et du compte si c'etait le dernier.
     *
     * Le refus est explicite plutot que silencieux : l'admin doit savoir ce qui
     * bloque, pas seulement que ca ne marche pas.
     */
    public function delete(int $id, DeleteStaffAccount $action): void
    {
        $membership = Receptionist::findOrFail($id);
        $nom = $membership->user?->name;

        try {
            $action->execute($membership, Auth::user());
        } catch (InvalidArgumentException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->notifySuccess(sprintf('Receptionniste supprime : %s.', $nom));

        if ($this->editingId === $id) {
            $this->cancel();
        }
    }

    public function render(): View
    {
        return view('livewire.admin.receptionist-manager', [
            'receptionists' => Receptionist::with('user')
                ->get()
                ->sortBy(fn (Receptionist $r) => $r->user->name)
                ->values(),
        ]);
    }
}
