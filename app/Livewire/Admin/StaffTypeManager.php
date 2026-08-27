<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\NotifiesUser;
use App\Models\StaffType;
use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Types de personnel administrables (v3.2.1, point 10).
 *
 * Deux chemins a la creation, et l'admin doit voir lequel il emprunte :
 *  - un type adosse a un role fixe reutilise une interface deja construite ;
 *  - un type sans role recoit /staff/{slug}, avec les seules sections cochees.
 *
 * L'apercu affiche en permanence la liste des sections qui apparaitront
 * reellement : l'admin doit comprendre ce qu'il cree avant qu'un membre du
 * personnel ne s'y connecte.
 */
class StaffTypeManager extends Component
{
    use NotifiesUser;

    public ?int $editingId = null;

    public string $name = '';

    /** '' = interface generique ; sinon un des trois roles reutilisables. */
    public string $matched_role = '';

    /** @var array<int, string> */
    public array $capabilities = [];

    /**
     * Les roles qu'un type peut reutiliser. `admin` en est volontairement
     * absent : l'administrateur n'a pas vocation a etre multiplie.
     *
     * @return array<string, string>
     */
    public function reusableRoles(): array
    {
        return [
            Roles::DOCTOR => Roles::label(Roles::DOCTOR),
            Roles::RECEPTIONIST => Roles::label(Roles::RECEPTIONIST),
            Roles::CASHIER => Roles::label(Roles::CASHIER),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'matched_role' => ['nullable', Rule::in(array_keys($this->reusableRoles()))],
            'capabilities' => ['array'],
            'capabilities.*' => [Rule::in(array_keys(StaffType::CAPABILITIES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => 'nom du type', 'matched_role' => 'role reutilise', 'capabilities' => 'fonctions'];
    }

    /**
     * L'apercu des sections que ce type verra reellement, recalcule a chaque
     * case cochee.
     *
     * @return array<int, string>
     */
    public function previewSections(): array
    {
        $modele = $this->draft();
        $sections = [];

        foreach (StaffType::CAPABILITIES as $capability => $meta) {
            if ($modele->can($capability)) {
                $sections[] = $meta['section'];
            }
        }

        // Le planning personnel est offert a tout le monde, sans capacite.
        $sections[] = 'Mon planning';

        return array_values(array_unique($sections));
    }

    /**
     * Le type tel qu'il serait si l'on enregistrait maintenant.
     *
     * Sert a l'apercu et au rendu des cases : plutot que de reimplementer les
     * regles de capacite dans le composant, on interroge le modele, seul
     * detenteur de ce qui est obligatoire et de ce qui ne l'est pas.
     */
    public function draft(): StaffType
    {
        $type = $this->editingId
            ? StaffType::findOrFail($this->editingId)->replicate()
            : new StaffType;

        $type->matched_role = $this->matched_role !== '' ? $this->matched_role : null;
        $type->capabilities = $this->capabilities;

        return $type;
    }

    /**
     * @return array<int, string> capacites imposees par le role choisi
     */
    public function requiredCapabilities(): array
    {
        return $this->draft()->requiredCapabilities();
    }

    /**
     * @return array<int, string> capacites que l'admin peut cocher
     */
    public function optionalCapabilities(): array
    {
        return $this->draft()->optionalCapabilities();
    }

    public function edit(int $typeId): void
    {
        $type = StaffType::findOrFail($typeId);

        $this->editingId = $type->getKey();
        $this->name = $type->name;
        $this->matched_role = (string) $type->matched_role;
        // Les obligatoires sont reintroduites : une base anterieure au v3.2.2
        // les a a null, et le formulaire doit malgre tout les montrer cochees.
        $this->capabilities = $type->normalizeCapabilities($type->capabilities ?? []);
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'matched_role', 'capabilities']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        $adosse = ($data['matched_role'] ?? '') !== '';

        // Le modele arbitre : il reintroduit les capacites obligatoires du role
        // et ecarte tout ce qui sort de son perimetre. Decocher une obligatoire
        // depuis le navigateur, ou en forger une par requete directe, ne change
        // donc rien a ce qui est enregistre.
        $attributs = [
            'name' => $data['name'],
            'matched_role' => $adosse ? $data['matched_role'] : null,
            'capabilities' => $this->draft()->normalizeCapabilities($data['capabilities'] ?? []),
        ];

        if ($this->editingId) {
            $type = StaffType::findOrFail($this->editingId);

            $attributs['slug'] = $adosse
                ? null
                : ($type->slug ?: StaffType::makeSlug($data['name'], $type->getKey()));

            $type->update($attributs);

            Audit::log(
                Audit::EVENT_STAFF_TYPE_UPDATED,
                sprintf('Type de personnel « %s » modifie.', $type->name),
                $type,
            );

            $this->notifySuccess('Type de personnel mis a jour.');
        } else {
            $attributs['slug'] = $adosse ? null : StaffType::makeSlug($data['name']);

            $type = StaffType::create($attributs);

            Audit::log(
                Audit::EVENT_STAFF_TYPE_CREATED,
                $adosse
                    ? sprintf('Type de personnel « %s » cree, adosse au role %s.', $type->name, $type->matched_role)
                    : sprintf(
                        'Type de personnel « %s » cree avec son interface /staff/%s (%d fonction(s)).',
                        $type->name,
                        $type->slug,
                        count($type->enabledOptionalCapabilities()),
                    ),
                $type,
            );

            $this->notifySuccess('Type de personnel cree.');
        }

        $this->cancel();
        $this->dispatch('types-de-personnel-mis-a-jour');
    }

    /**
     * Meme garde-fou que partout ailleurs : un type encore porte par quelqu'un
     * n'est pas supprimable.
     */
    public function delete(int $typeId): void
    {
        $type = StaffType::withCount('members')->findOrFail($typeId);

        if ($type->members_count > 0) {
            $this->notifyError(sprintf(
                'Le type « %s » ne peut pas etre supprime : %d personne(s) le portent encore.',
                $type->name,
                $type->members_count,
            ));

            return;
        }

        $nom = $type->name;
        $type->delete();

        Audit::log(Audit::EVENT_STAFF_TYPE_DELETED, sprintf('Type de personnel « %s » supprime.', $nom));

        $this->notifySuccess('Type de personnel supprime.');
        $this->dispatch('types-de-personnel-mis-a-jour');
    }

    public function render(): View
    {
        return view('livewire.admin.staff-type-manager', [
            'types' => StaffType::withCount('members')->orderBy('name')->get(),
            'allCapabilities' => StaffType::CAPABILITIES,
        ]);
    }
}
