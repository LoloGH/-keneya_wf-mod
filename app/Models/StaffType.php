<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Type de personnel administrable (v3.2.1, point 10).
 *
 * Un type adosse a un role (`matched_role`) reutilise une des quatre interfaces
 * existantes. Un type sans role recoit l'interface generique /staff/{slug},
 * composee des seules briques cochees dans `capabilities`.
 *
 * Ce n'est pas un generateur de code : les capacites listees ici sont celles
 * que l'application sait deja faire. Un metier qui demande une logique
 * entierement nouvelle demande toujours du developpement.
 */
class StaffType extends Model
{
    use HasFactory, RecordsActivity;

    /** File d'attente du service de rattachement, avec « Appeler le suivant ». */
    public const CAP_QUEUE = 'has_queue';

    public const CAP_SEND_REFERRAL = 'can_send_referral';

    public const CAP_RECEIVE_REFERRAL = 'can_receive_referral';

    public const CAP_VIEW_DOSSIER = 'can_view_dossier';

    public const CAP_ACCEPT_PAYMENT = 'can_accept_payment';

    public const CAP_CLOSE_VISIT = 'can_close_visit';

    public const CAP_PRINT_TICKET = 'can_print_ticket';

    /** Soins programmes des patients hospitalises (v3.2.1, point 11). */
    public const CAP_CARE_TASKS = 'has_care_tasks';

    /**
     * Prescrire des soins recurrents a un patient hospitalise (v3.3.1).
     *
     * Distincte de CAP_CARE_TASKS, qui execute, et de
     * CAP_ADMIT_HOSPITALIZATION, qui admet : prescrire un traitement n'est ni
     * l'un ni l'autre. Le droit etait jusqu'ici implicite, il venait avec
     * l'hospitalisation, ce qui empechait un etablissement de dissocier les
     * deux actes.
     */
    public const CAP_PRESCRIBE_CARE = 'can_prescribe_care';

    public const CAP_PRESCRIBE = 'can_prescribe';

    public const CAP_SCHEDULE_APPOINTMENT = 'can_schedule_appointment';

    public const CAP_ADMIT_HOSPITALIZATION = 'can_admit_hospitalization';

    public const CAP_REGISTER_PATIENT = 'can_register_patient';

    public const CAP_REGISTER_VISITOR = 'can_register_visitor';

    /**
     * Ouverture du dossier medical complet, porte par le module keneya/dme
     * (v3.3.0). Distincte de CAP_VIEW_DOSSIER, qui n'ouvre que le dossier
     * WorkFlow : passages, renvois, pieces jointes. Celle-ci donne acces a
     * l'antecedent medical, aux consultations, aux ordonnances et aux
     * examens : elle se coche a part, et pour les seuls comptes qui en ont
     * l'usage clinique.
     */
    public const CAP_ACCESS_DME = 'can_access_dme';

    /**
     * Rediger une consultation medicale (v3.3.1).
     *
     * Le formulaire vit dans /service mais ecrit dans le dossier medical, pas
     * dans WorkFlow. Capacite distincte de CAP_ACCESS_DME : lire un dossier et
     * y ecrire un acte ne sont pas le meme droit, et tout medecin qui consulte
     * un dossier n'a pas vocation a le remplir.
     */
    public const CAP_RECORD_CONSULTATION = 'can_record_consultation';

    /**
     * Consigner un antecedent au dossier medical (v3.3.1).
     *
     * Une capacite par formulaire, et non une seule pour tout le dossier : un
     * hopital peut vouloir qu'une infirmiere releve les allergies sans qu'elle
     * redige des consultations. Le decoupage des droits suit celui des ecrans.
     */
    public const CAP_RECORD_HISTORY = 'can_record_history';

    /** Consigner une allergie au dossier medical (v3.3.1). */
    public const CAP_RECORD_ALLERGIES = 'can_record_allergies';

    /** Consigner un traitement habituel au dossier medical (v3.3.1). */
    public const CAP_RECORD_MEDICATIONS = 'can_record_medications';

    /** Demander un examen biologique au dossier medical (v3.3.1). */
    public const CAP_ORDER_LABORATORY = 'can_order_laboratory';

    /** Demander un examen d'imagerie au dossier medical (v3.3.1). */
    public const CAP_ORDER_IMAGING = 'can_order_imaging';

    /** Verser un document au dossier medical (v3.3.1). */
    public const CAP_RECORD_DOCUMENTS = 'can_record_documents';

    /**
     * Libelle de chaque capacite et section qu'elle fait apparaitre.
     *
     * C'est cette table qui alimente l'apercu montre a l'admin au moment de
     * creer un type : il doit comprendre ce qu'il vient de creer avant qu'un
     * membre du personnel ne s'y connecte.
     *
     * @var array<string, array{label: string, section: string}>
     */
    public const CAPABILITIES = [
        self::CAP_QUEUE => [
            'label' => 'File d\'attente du service',
            'section' => 'File d\'attente, appeler le patient suivant',
        ],
        self::CAP_SEND_REFERRAL => [
            'label' => 'Envoyer un patient vers un autre service',
            'section' => 'Envoyer vers un service',
        ],
        self::CAP_RECEIVE_REFERRAL => [
            'label' => 'Recevoir des renvois et saisir un resultat',
            'section' => 'Renvois recus',
        ],
        self::CAP_VIEW_DOSSIER => [
            'label' => 'Consulter le dossier d\'un patient',
            'section' => 'Dossier patient',
        ],
        self::CAP_ACCEPT_PAYMENT => [
            'label' => 'Encaisser un paiement',
            'section' => 'Encaissement',
        ],
        self::CAP_CLOSE_VISIT => [
            'label' => 'Cloturer un dossier',
            'section' => 'Cloture de dossier (dans la file)',
        ],
        self::CAP_PRINT_TICKET => [
            'label' => 'Imprimer un ticket',
            'section' => 'Impression du ticket (dans la file)',
        ],
        self::CAP_CARE_TASKS => [
            'label' => 'Executer les soins programmes',
            'section' => 'Soins programmes des patients hospitalises',
        ],
        self::CAP_PRESCRIBE_CARE => [
            'label' => 'Prescrire des soins a un patient hospitalise',
            'section' => 'Prescription de soins (dans « Patients hospitalises »)',
        ],
        self::CAP_PRESCRIBE => [
            'label' => 'Rediger une ordonnance et une conclusion',
            'section' => 'Fin de consultation',
        ],
        self::CAP_SCHEDULE_APPOINTMENT => [
            'label' => 'Donner un rendez-vous',
            'section' => 'Rendez-vous',
        ],
        self::CAP_ADMIT_HOSPITALIZATION => [
            'label' => 'Hospitaliser un patient',
            'section' => 'Patients hospitalises',
        ],
        self::CAP_REGISTER_PATIENT => [
            'label' => 'Enregistrer un patient',
            'section' => 'Nouveau patient',
        ],
        self::CAP_REGISTER_VISITOR => [
            'label' => 'Enregistrer un visiteur',
            'section' => 'Visiteur',
        ],
        self::CAP_ACCESS_DME => [
            'label' => 'Ouvrir le dossier medical complet (DME)',
            'section' => 'Dossier medical complet (depuis « Mes patients »)',
        ],
        self::CAP_RECORD_CONSULTATION => [
            'label' => 'Rediger une consultation medicale',
            'section' => 'Consultation, motif, constantes, examen, diagnostics',
        ],
        self::CAP_RECORD_HISTORY => [
            'label' => 'Consigner un antecedent',
            'section' => 'Antecedents, personnels, chirurgicaux, familiaux',
        ],
        self::CAP_RECORD_ALLERGIES => [
            'label' => 'Consigner une allergie',
            'section' => 'Allergies, allergene, reaction, severite',
        ],
        self::CAP_RECORD_MEDICATIONS => [
            'label' => 'Consigner un traitement habituel',
            'section' => 'Traitements, ce que le patient prend deja',
        ],
        self::CAP_ORDER_LABORATORY => [
            'label' => 'Demander un examen biologique',
            'section' => 'Laboratoire, demande d\'analyses',
        ],
        self::CAP_ORDER_IMAGING => [
            'label' => 'Demander un examen d\'imagerie',
            'section' => 'Imagerie, echographie, radiographie, scanner',
        ],
        self::CAP_RECORD_DOCUMENTS => [
            'label' => 'Verser un document au dossier medical',
            'section' => 'Documents, comptes rendus et resultats',
        ],
    ];

    /**
     * Ce que chaque role code exige, et ce qu'il permet de moduler (v3.2.2).
     *
     * Les capacites **obligatoires** font le role : les decocher laisserait une
     * interface amputee de ce qui la definit, un medecin sans file d'attente
     * n'est plus un medecin. Elles vivent ici, dans le code, et non en base :
     * une regle qui tient l'application debout ne se modifie pas depuis un
     * formulaire.
     *
     * Les capacites **optionnelles** sont de vrais choix d'organisation : cet
     * hopital fait-il prescrire ses sages-femmes, hospitaliser ses urgentistes ?
     * L'admin tranche, type par type.
     *
     * Un type sans `matched_role` n'a aucune capacite obligatoire : tout y est
     * optionnel, comme depuis le v3.2.1.
     *
     * @var array<string, array{required: array<int, string>, optional: array<int, string>}>
     */
    public const ROLE_CAPABILITIES = [
        Roles::DOCTOR => [
            'required' => [
                self::CAP_QUEUE,
                self::CAP_SEND_REFERRAL,
                self::CAP_RECEIVE_REFERRAL,
                self::CAP_VIEW_DOSSIER,
                self::CAP_CLOSE_VISIT,
            ],
            'optional' => [
                self::CAP_PRESCRIBE,
                self::CAP_SCHEDULE_APPOINTMENT,
                self::CAP_ADMIT_HOSPITALIZATION,
                self::CAP_CARE_TASKS,
                self::CAP_PRESCRIBE_CARE,
                self::CAP_ACCESS_DME,
                self::CAP_RECORD_CONSULTATION,
                self::CAP_RECORD_HISTORY,
                self::CAP_RECORD_ALLERGIES,
                self::CAP_RECORD_MEDICATIONS,
                self::CAP_ORDER_LABORATORY,
                self::CAP_ORDER_IMAGING,
                self::CAP_RECORD_DOCUMENTS,
            ],
        ],
        Roles::RECEPTIONIST => [
            'required' => [
                self::CAP_VIEW_DOSSIER,
                self::CAP_REGISTER_PATIENT,
            ],
            'optional' => [
                self::CAP_REGISTER_VISITOR,
                self::CAP_SCHEDULE_APPOINTMENT,
                self::CAP_PRINT_TICKET,
            ],
        ],
        Roles::CASHIER => [
            'required' => [
                self::CAP_QUEUE,
                self::CAP_ACCEPT_PAYMENT,
            ],
            'optional' => [
                self::CAP_PRINT_TICKET,
            ],
        ],
    ];

    protected $fillable = ['name', 'matched_role', 'slug', 'capabilities'];

    protected function casts(): array
    {
        return ['capabilities' => 'array'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    /** Ce type reutilise-t-il une des quatre interfaces deja construites ? */
    public function usesFixedRole(): bool
    {
        return filled($this->matched_role);
    }

    /**
     * Les capacites que ce type ne peut pas perdre.
     *
     * @return array<int, string>
     */
    public function requiredCapabilities(): array
    {
        return self::ROLE_CAPABILITIES[$this->matched_role]['required'] ?? [];
    }

    /**
     * Les capacites que l'admin peut cocher ou decocher sur ce type.
     *
     * Pour un type sans role, c'est tout le catalogue : rien n'y est impose.
     *
     * @return array<int, string>
     */
    public function optionalCapabilities(): array
    {
        if (! $this->usesFixedRole()) {
            return array_keys(self::CAPABILITIES);
        }

        return self::ROLE_CAPABILITIES[$this->matched_role]['optional'] ?? [];
    }

    /**
     * Les capacites optionnelles effectivement activees sur ce type.
     *
     * C'est ce que compte la colonne « Fonctions » : un tiret ne disait rien,
     * et compter les obligatoires n'apprendrait rien non plus puisqu'elles sont
     * les memes pour tous les types d'un meme role.
     *
     * @return array<int, string>
     */
    public function enabledOptionalCapabilities(): array
    {
        return array_values(array_intersect($this->optionalCapabilities(), $this->capabilities ?? []));
    }

    /**
     * Ce type sait-il faire ceci ?
     *
     * Une capacite obligatoire du role repond oui sans meme etre stockee : elle
     * ne depend pas de ce que contient la colonne, et une base incomplete ne
     * doit pas amputer une interface.
     */
    public function can(string $capability): bool
    {
        return in_array($capability, $this->requiredCapabilities(), true)
            || in_array($capability, $this->capabilities ?? [], true);
    }

    /**
     * Normalise une liste soumise par l'admin : les obligatoires sont
     * reintroduites, l'inconnu et le hors-perimetre sont ecartes.
     *
     * C'est ici que se joue le refus serveur : decocher une capacite
     * obligatoire depuis le navigateur, ou en forger une par requete directe,
     * ne change rien a ce qui est enregistre.
     *
     * @param  array<int, string>  $soumises
     * @return array<int, string>
     */
    public function normalizeCapabilities(array $soumises): array
    {
        $retenues = array_intersect($soumises, $this->optionalCapabilities());

        return array_values(array_unique(array_merge($this->requiredCapabilities(), $retenues)));
    }

    /**
     * Les sections qui apparaitront reellement, dans l'ordre.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function sections(): array
    {
        $sections = [];

        foreach (self::CAPABILITIES as $capability => $meta) {
            if ($this->can($capability)) {
                $sections[] = ['key' => $capability, 'label' => $meta['section']];
            }
        }

        return $sections;
    }

    /**
     * L'interface de ce type : une route nommee pour un role fixe, l'URL
     * generique sinon.
     */
    public function homeUrl(): ?string
    {
        if ($this->usesFixedRole()) {
            $route = Roles::homeRoute($this->matched_role);

            return $route ? route($route) : null;
        }

        return $this->slug ? route('staff.home', $this->slug) : null;
    }

    public static function makeSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'personnel';
        $slug = $base;
        $suffixe = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffixe++;
        }

        return $slug;
    }

    public static function auditLabel(): string
    {
        return 'Type de personnel';
    }
}
