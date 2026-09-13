<?php

declare(strict_types=1);

namespace Keneya\Dme\Support;

/**
 * Référentiel unique des rôles et permissions (§31-32).
 *
 * Ce fichier est la seule source de vérité : le seeder, les policies, les
 * tests d'autorisation et l'écran d'administration des utilisateurs le
 * consomment. Ajouter une permission ici suffit à la rendre disponible
 * partout, ce qui évite les divergences entre le backend et l'interface.
 *
 * Phase 2 (§60) : cette classe est le point d'ancrage prévu pour se
 * raccorder au RBAC central de Keneya Workflow, la correspondance se
 * fera rôle à rôle, sans toucher aux vérifications disséminées dans le code.
 */
final class Rbac
{
    // Rôles (§31)
    public const ROLE_ADMIN = 'administrateur';
    public const ROLE_DOCTOR = 'medecin';
    public const ROLE_NURSE = 'infirmier';
    public const ROLE_LAB = 'laboratoire';
    public const ROLE_RADIOLOGY = 'radiologie';
    public const ROLE_PHARMACIST = 'pharmacien';
    public const ROLE_RECEPTION = 'reception';

    /**
     * Les rôles de l'application hôte qui correspondent à ce rôle du DME.
     *
     * Le module a son propre vocabulaire clinique, médecin, infirmier,
     * laboratoire, et une application hôte a le sien, souvent construit
     * autour de ses interfaces plutôt que des métiers. Monté dans Keneya
     * Workflow, « medecin » s'y appelle « doctor ». Sans traduction, une
     * simple liste de praticiens fait tomber la page : le scope `role()` de
     * spatie lève une exception dès qu'un rôle n'existe pas en base.
     *
     * L'hôte déclare la correspondance dans `config/dme.php` :
     *
     *     'roles' => ['medecin' => 'doctor', 'reception' => 'receptionist'],
     *
     * Une valeur peut être une chaîne ou une liste. Un rôle non déclaré se
     * traduit par lui-même, ce qui laisse le module fonctionner seul.
     *
     * Les rôles absents de la base sont écartés ici, et non plus loin : une
     * liste de praticiens vide se lit, une page en 500 non.
     *
     * @return list<string>
     */
    public static function hostRoles(string $role): array
    {
        $declares = (array) config('dme.roles', []);

        $noms = array_values(array_filter(array_map(
            static fn ($nom) => is_string($nom) ? trim($nom) : '',
            (array) ($declares[$role] ?? $role),
        )));

        if ($noms === []) {
            return [];
        }

        return \Spatie\Permission\Models\Role::query()
            ->whereIn('name', $noms)
            ->pluck('name')
            ->all();
    }

    /**
     * Libellés d'affichage des rôles.
     *
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrateur',
            self::ROLE_DOCTOR => 'Médecin',
            self::ROLE_NURSE => 'Infirmier',
            self::ROLE_LAB => 'Laboratoire',
            self::ROLE_RADIOLOGY => 'Radiologie',
            self::ROLE_PHARMACIST => 'Pharmacien',
            self::ROLE_RECEPTION => 'Réception',
        ];
    }

    /**
     * Toutes les permissions granulaires, groupées par domaine (§32).
     *
     * @return array<string, array<string, string>>
     */
    public static function permissionGroups(): array
    {
        return [
            'Patients' => [
                'patients.view' => 'Consulter les dossiers patients',
                'patients.create' => 'Créer un patient',
                'patients.update' => 'Modifier un patient',
                'patients.delete' => 'Archiver un patient',
                'patients.purge' => 'Supprimer définitivement un dossier',
            ],
            'Consultations' => [
                'consultations.view' => 'Consulter les consultations',
                'consultations.create' => 'Créer une consultation',
                'consultations.update' => 'Modifier une consultation',
            ],
            'Constantes et soins' => [
                'vitals.view' => 'Consulter les constantes',
                'vitals.create' => 'Enregistrer des constantes',
                'nursing.view' => 'Consulter les soins infirmiers',
                'nursing.create' => 'Enregistrer un soin infirmier',
            ],
            'Soins programmés' => [
                'care_orders.view' => 'Consulter les soins programmés',
                'care_orders.create' => 'Prescrire un soin',
                'care_orders.assign' => 'Confier un soin à un soignant',
                'care_orders.execute' => 'Réaliser ou refuser un soin',
                'care_orders.cancel' => 'Annuler un soin programmé',
            ],
            'Diagnostics' => [
                'diagnoses.view' => 'Consulter les diagnostics',
                'diagnoses.create' => 'Poser un diagnostic',
            ],
            'Ordonnances' => [
                'prescriptions.view' => 'Consulter les ordonnances',
                'prescriptions.create' => 'Créer une ordonnance',
                'prescriptions.validate' => 'Valider une ordonnance',
                'prescriptions.dispense' => 'Délivrer une ordonnance',
            ],
            'Laboratoire' => [
                'laboratory.view' => 'Consulter le laboratoire',
                'laboratory.orders.create' => 'Créer une demande d\'analyse',
                'laboratory.results.create' => 'Saisir un résultat',
                'laboratory.results.validate' => 'Valider un résultat',
            ],
            'Imagerie' => [
                'imaging.view' => 'Consulter l\'imagerie',
                'imaging.create' => 'Créer une demande d\'imagerie',
                'imaging.reports.create' => 'Rédiger un compte rendu',
            ],
            'Hospitalisation' => [
                'hospitalizations.view' => 'Consulter les hospitalisations',
                'hospitalizations.create' => 'Admettre un patient',
                'hospitalizations.update' => 'Suivre / faire sortir un patient',
            ],
            'Rendez-vous' => [
                'appointments.view' => 'Consulter les rendez-vous',
                'appointments.manage' => 'Gérer les rendez-vous',
            ],
            'Documents' => [
                'documents.view' => 'Consulter les documents',
                'documents.upload' => 'Importer un document',
                'documents.download' => 'Télécharger un document',
            ],
            'Administration' => [
                'audit.view' => 'Consulter le journal d\'audit',
                'users.manage' => 'Gérer les utilisateurs',
                'settings.manage' => 'Gérer les paramètres',
                'roles.manage' => 'Modifier les rôles et permissions',
                'sms.view' => 'Consulter l\'historique SMS',
                'sms.send' => 'Envoyer un SMS',
            ],
        ];
    }

    /**
     * Liste plate de toutes les permissions.
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return array_merge(...array_map(
            static fn (array $group) => array_keys($group),
            array_values(self::permissionGroups())
        ));
    }

    /**
     * Permissions requises pour que l'administration reste possible.
     *
     * Le rôle administrateur les conserve quoi qu'il arrive : les retirer
     * fermerait définitivement l'accès à l'écran qui permet de les rendre,
     * sans autre issue qu'une intervention en base.
     *
     * @return list<string>
     */
    public static function lockedAdminPermissions(): array
    {
        return ['users.manage', 'settings.manage', 'roles.manage'];
    }

    /**
     * Permissions attribuées à chaque rôle au premier amorçage (§31).
     *
     * Depuis que la matrice est modifiable dans l'écran Paramètres, ce
     * tableau n'est plus la vérité courante : il ne sert qu'à peupler la
     * base au premier `migrate --seed`. L'état réel se lit dans les rôles
     * persistés, via Rbac::persistedRolePermissions().
     *
     * L'administrateur reçoit l'intégralité des permissions ; les autres
     * rôles sont volontairement restreints à leur périmètre métier.
     *
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        return [
            self::ROLE_ADMIN => self::allPermissions(),

            // Médecin : parcours clinique complet.
            self::ROLE_DOCTOR => [
                'patients.view', 'patients.create', 'patients.update',
                'consultations.view', 'consultations.create', 'consultations.update',
                'vitals.view', 'vitals.create',
                'nursing.view',
                'care_orders.view', 'care_orders.create', 'care_orders.assign', 'care_orders.cancel',
                'diagnoses.view', 'diagnoses.create',
                'prescriptions.view', 'prescriptions.create', 'prescriptions.validate',
                'laboratory.view', 'laboratory.orders.create',
                'imaging.view', 'imaging.create',
                'hospitalizations.view', 'hospitalizations.create', 'hospitalizations.update',
                'appointments.view', 'appointments.manage',
                'documents.view', 'documents.upload', 'documents.download',
                'sms.view', 'sms.send',
            ],

            // Infirmier : constantes et soins.
            self::ROLE_NURSE => [
                'patients.view',
                'consultations.view',
                'vitals.view', 'vitals.create',
                'nursing.view', 'nursing.create',
                'care_orders.view', 'care_orders.execute',
                'hospitalizations.view', 'hospitalizations.update',
                'appointments.view',
                'documents.view',
            ],

            // Laboratoire : demandes et résultats d'analyse.
            self::ROLE_LAB => [
                'patients.view',
                'laboratory.view', 'laboratory.results.create', 'laboratory.results.validate',
                'documents.view', 'documents.upload',
            ],

            // Radiologie : examens et comptes rendus d'imagerie.
            self::ROLE_RADIOLOGY => [
                'patients.view',
                'imaging.view', 'imaging.reports.create',
                'documents.view', 'documents.upload',
            ],

            // Pharmacien : ordonnances uniquement.
            self::ROLE_PHARMACIST => [
                'patients.view',
                'prescriptions.view', 'prescriptions.dispense',
                'documents.view',
            ],

            // Réception : administratif, patients et rendez-vous.
            self::ROLE_RECEPTION => [
                'patients.view', 'patients.create', 'patients.update',
                'appointments.view', 'appointments.manage',
                'sms.view', 'sms.send',
            ],
        ];
    }

    /**
     * Libellés mémorisés le temps de la requête, pour allRoleLabels().
     *
     * @var array<string, string>|null
     */
    private static ?array $allLabelsCache = null;

    /**
     * Libellés de TOUS les rôles, y compris ceux créés depuis l'écran
     * Paramètres : contrairement à roleLabels(), qui ne connaît que les
     * sept rôles d'origine.
     *
     * Mémorisé en mémoire pour la durée de la requête : cette méthode est
     * appelée depuis des boucles d'affichage (journal d'audit, liste des
     * utilisateurs) où une requête par appel serait un N+1.
     *
     * @return array<string, string>
     */
    public static function allRoleLabels(): array
    {
        if (self::$allLabelsCache !== null) {
            return self::$allLabelsCache;
        }

        $builtin = self::roleLabels();

        $custom = \Spatie\Permission\Models\Role::query()
            ->whereNotIn('name', array_keys($builtin))
            ->get(['name', 'label'])
            ->mapWithKeys(static fn ($role) => [
                $role->name => $role->label ?: \Illuminate\Support\Str::headline($role->name),
            ])
            ->all();

        return self::$allLabelsCache = $builtin + $custom;
    }

    /**
     * État courant de la matrice, tel qu'il est enregistré en base.
     *
     * C'est cette méthode que doivent consulter l'écran Paramètres et les
     * tests : après une modification par l'administrateur, le tableau
     * d'amorçage ci-dessus ne décrit plus la réalité.
     *
     * @return array<string, list<string>>
     */
    public static function persistedRolePermissions(): array
    {
        return \Spatie\Permission\Models\Role::with('permissions:id,name')
            ->get()
            ->mapWithKeys(static fn ($role) => [
                $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
            ])
            ->all();
    }
}
