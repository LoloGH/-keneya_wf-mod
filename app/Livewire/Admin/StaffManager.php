<?php

namespace App\Livewire\Admin;

use App\Actions\DeleteStaffAccount;
use App\Livewire\Concerns\NotifiesAdmin;
use App\Models\Cashier;
use App\Models\Doctor;
use App\Models\Receptionist;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\StaffType;
use App\Models\User;
use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Gestion de tout le personnel (v3.2.3, points 2 et 3).
 *
 * Cette section remplace les trois formulaires separes — « Medecins »,
 * « Receptionnistes », « Interfaces dediees » — qui presentaient chacun un
 * sous-ensemble des types de personnel. L'admin choisissait un type parmi ceux
 * que la section voulait bien montrer, ce qui rendait un Caissier impossible a
 * creer et un service de caisse impossible a choisir.
 *
 * Ici les deux menus refletent les tables : tous les `staff_types`, tous les
 * services. C'est le type choisi qui decide de la suite — son `matched_role`
 * designe a la fois le role Spatie synchronise et la table de rattachement :
 *
 *   doctor       -> doctors       (service et telephone)
 *   receptionist -> receptionists
 *   cashier      -> cashiers
 *   aucun role   -> staff_members (service, interface generique /staff/{slug})
 *
 * Sans cette correspondance, elargir le menu aurait cree des comptes portant
 * le role « medecin » avec un type « Caissier » : la personne aurait atterri
 * sur /service avec les capacites d'un caissier.
 */
class StaffManager extends Component
{
    use NotifiesAdmin;

    /**
     * Rattachement en cours de modification, sous la forme « doctor:12 ».
     *
     * Les quatre tables ont chacune leurs identifiants : une simple cle
     * numerique ne suffirait pas a designer une ligne sans ambiguite.
     */
    public ?string $editingRef = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?int $staff_type_id = null;

    public ?int $service_id = null;

    public string $phone = '';

    /** @var array<string, class-string<Model>> */
    private const ATTACHMENTS = [
        Roles::DOCTOR => Doctor::class,
        Roles::RECEPTIONIST => Receptionist::class,
        Roles::CASHIER => Cashier::class,
    ];

    #[On('services-mis-a-jour')]
    #[On('types-de-personnel-mis-a-jour')]
    public function refreshLists(): void
    {
        // Un nouveau rendu suffit : les deux menus sont relus a chaque rendu.
    }

    /**
     * Le type choisi dans le formulaire, ou null tant que rien n'est choisi.
     */
    public function selectedType(): ?StaffType
    {
        return $this->staff_type_id ? StaffType::find($this->staff_type_id) : null;
    }

    /**
     * Un service n'a de sens que pour qui exerce dans un service : un medecin
     * et le personnel generique. La receptionniste et le caissier n'y sont pas
     * rattaches — l'accueil et les caisses ne sont pas leur service, ce sont
     * leurs interfaces.
     */
    public function needsService(): bool
    {
        $role = $this->selectedType()?->matched_role;

        return $this->staff_type_id !== null
            && ($role === Roles::DOCTOR || $role === null);
    }

    /**
     * Le telephone sert a l'envoi des resultats par SMS : seule la table
     * `doctors` le porte.
     */
    public function needsPhone(): bool
    {
        return $this->selectedType()?->matched_role === Roles::DOCTOR;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingUserId()),
            ],
            'password' => [$this->editingRef ? 'nullable' : 'required', 'string', 'min:8'],
            // Tous les types, sans exception : le menu reflete la table.
            'staff_type_id' => ['required', 'integer', 'exists:staff_types,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            // Tous les services, caisse comprise : un agent peut etre affecte
            // a une caisse comme a un service de soins.
            'service_id' => $this->needsService()
                ? ['required', 'integer', 'exists:services,id']
                : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
            'staff_type_id' => 'type de personnel',
            'phone' => 'telephone',
            'service_id' => 'service',
        ];
    }

    public function edit(string $ref): void
    {
        $membership = $this->resolve($ref);
        $user = $membership->user;

        $this->editingRef = $ref;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->staff_type_id = $membership instanceof StaffMember
            ? $membership->staff_type_id
            : ($user->staff_type_id ?: $this->defaultTypeFor($this->roleOf($ref))?->getKey());
        $this->service_id = $membership instanceof Doctor || $membership instanceof StaffMember
            ? $membership->service_id
            : null;
        $this->phone = $membership instanceof Doctor ? (string) $membership->phone : '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingRef', 'name', 'email', 'password', 'staff_type_id', 'service_id', 'phone']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();
        $type = StaffType::findOrFail($data['staff_type_id']);

        if ($this->editingRef && $this->roleOf($this->editingRef) !== $type->matched_role) {
            // Changer de role changerait de table de rattachement, donc
            // d'interface et d'historique. Le refus est explicite : l'admin
            // supprime le rattachement et en cree un autre, ce qui passe par
            // les garde-fous de DeleteStaffAccount.
            $this->notifyError(
                'Un compte ne change pas de role. Supprimez ce rattachement, puis creez-en un nouveau avec le type voulu.'
            );

            return;
        }

        DB::transaction(function () use ($data, $type): void {
            $this->editingRef
                ? $this->update($data, $type)
                : $this->create($data, $type);
        });

        $this->notifySuccess($this->editingRef ? 'Personnel mis a jour.' : 'Personnel cree.');

        $this->cancel();
        $this->dispatch('personnels-mis-a-jour');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function update(array $data, StaffType $type): void
    {
        $membership = $this->resolve($this->editingRef);
        $user = $membership->user;

        $user->update(array_filter([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?: null,
        ], fn ($value) => $value !== null));

        // Le type decide des fonctions optionnelles offertes a ce compte
        // (v3.2.2). Un type generique est porte par staff_members, pas par le
        // compte : c'est lui qui designe l'interface /staff/{slug}.
        $user->update(['staff_type_id' => $type->usesFixedRole() ? $type->getKey() : null]);

        if ($membership instanceof StaffMember) {
            $membership->update([
                'staff_type_id' => $type->getKey(),
                'service_id' => $data['service_id'],
            ]);

            return;
        }

        if ($membership instanceof Doctor) {
            $previousServiceId = $membership->service_id;

            $membership->update([
                'service_id' => $data['service_id'],
                'phone' => $data['phone'] ?: null,
            ]);

            if ((int) $previousServiceId !== (int) $data['service_id']) {
                Audit::log(
                    Audit::EVENT_DOCTOR_REASSIGNED,
                    sprintf('%s reaffecte vers %s.', $user->name, $membership->service()->first()?->name),
                    $membership,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(array $data, StaffType $type): void
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'staff_type_id' => $type->usesFixedRole() ? $type->getKey() : null,
        ]);

        if ($type->usesFixedRole()) {
            $user->syncRoles([$type->matched_role]);
        }

        [$membership, $event, $libelle] = match ($type->matched_role) {
            Roles::DOCTOR => [
                Doctor::create([
                    'user_id' => $user->getKey(),
                    'service_id' => $data['service_id'],
                    'phone' => $data['phone'] ?: null,
                ]),
                Audit::EVENT_DOCTOR_CREATED,
                sprintf('Medecin %s cree.', $user->name),
            ],
            Roles::RECEPTIONIST => [
                Receptionist::create(['user_id' => $user->getKey()]),
                Audit::EVENT_RECEPTIONIST_CREATED,
                sprintf('Receptionniste %s creee.', $user->name),
            ],
            Roles::CASHIER => [
                Cashier::create(['user_id' => $user->getKey()]),
                Audit::EVENT_STAFF_MEMBER_CREATED,
                sprintf('Caissier %s cree.', $user->name),
            ],
            default => [
                $member = StaffMember::create([
                    'user_id' => $user->getKey(),
                    'staff_type_id' => $type->getKey(),
                    'service_id' => $data['service_id'],
                ]),
                Audit::EVENT_STAFF_MEMBER_CREATED,
                sprintf(
                    '%s cree comme %s au service %s.',
                    $user->name,
                    $type->name,
                    $member->service()->first()?->name ?? '—',
                ),
            ],
        };

        Audit::log($event, $libelle, $membership);
    }

    /**
     * Suppression du rattachement, et du compte si c'etait le dernier.
     *
     * Le refus est explicite plutot que silencieux : l'admin doit savoir ce qui
     * bloque, pas seulement que ca ne marche pas.
     */
    public function delete(string $ref, DeleteStaffAccount $action): void
    {
        $membership = $this->resolve($ref);
        $nom = $membership->user?->name;

        try {
            $action->execute($membership, Auth::user());
        } catch (InvalidArgumentException $e) {
            $this->notifyError($e->getMessage());

            return;
        }

        $this->notifySuccess(sprintf('Personnel supprime : %s.', $nom));

        if ($this->editingRef === $ref) {
            $this->cancel();
        }

        $this->dispatch('personnels-mis-a-jour');
        $this->dispatch('medecins-mis-a-jour');
    }

    private function roleOf(string $ref): ?string
    {
        return match (explode(':', $ref)[0]) {
            'doctor' => Roles::DOCTOR,
            'receptionist' => Roles::RECEPTIONIST,
            'cashier' => Roles::CASHIER,
            default => null,
        };
    }

    private function defaultTypeFor(?string $role): ?StaffType
    {
        return $role
            ? StaffType::where('matched_role', $role)->orderBy('id')->first()
            : null;
    }

    private function editingUserId(): ?int
    {
        return $this->editingRef ? $this->resolve($this->editingRef)->user_id : null;
    }

    private function resolve(string $ref): Doctor|Receptionist|Cashier|StaffMember
    {
        [$kind, $id] = array_pad(explode(':', $ref, 2), 2, null);

        $model = match ($kind) {
            'doctor' => Doctor::class,
            'receptionist' => Receptionist::class,
            'cashier' => Cashier::class,
            'staff' => StaffMember::class,
            default => throw new InvalidArgumentException('Rattachement inconnu.'),
        };

        return $model::with('user')->findOrFail((int) $id);
    }

    /**
     * Tout le personnel, les quatre tables reunies et triees par nom : une
     * seule liste, pour que l'admin n'ait pas a deviner dans quelle section
     * chercher une personne.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function memberships(): Collection
    {
        $lignes = collect();

        foreach (Doctor::with(['user', 'service'])->get() as $doctor) {
            $lignes->push([
                'ref' => 'doctor:'.$doctor->getKey(),
                'user' => $doctor->user,
                'type' => $doctor->user->staffType()?->name ?? Roles::label(Roles::DOCTOR),
                'service' => $doctor->service?->name,
                'phone' => $doctor->phone,
            ]);
        }

        foreach (Receptionist::with('user')->get() as $receptionist) {
            $lignes->push([
                'ref' => 'receptionist:'.$receptionist->getKey(),
                'user' => $receptionist->user,
                'type' => $receptionist->user->staffType()?->name ?? Roles::label(Roles::RECEPTIONIST),
                'service' => null,
                'phone' => null,
            ]);
        }

        foreach (Cashier::with('user')->get() as $cashier) {
            $lignes->push([
                'ref' => 'cashier:'.$cashier->getKey(),
                'user' => $cashier->user,
                'type' => $cashier->user->staffType()?->name ?? Roles::label(Roles::CASHIER),
                'service' => null,
                'phone' => null,
            ]);
        }

        foreach (StaffMember::with(['user', 'staffType', 'service'])->get() as $member) {
            $lignes->push([
                'ref' => 'staff:'.$member->getKey(),
                'user' => $member->user,
                'type' => $member->staffType?->name,
                'service' => $member->service?->name,
                'phone' => null,
            ]);
        }

        return $lignes
            ->filter(fn (array $ligne) => $ligne['user'] !== null)
            ->sortBy(fn (array $ligne) => $ligne['user']->name)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.admin.staff-manager', [
            'personnels' => $this->memberships(),
            'staffTypes' => StaffType::orderBy('name')->get(),
            'services' => Service::orderBy('name')->get(),
        ]);
    }
}
