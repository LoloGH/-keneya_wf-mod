<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Dme\PatientProjection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Keneya\Dme\Models\ImagingReport as CompteRenduImagerie;
use Keneya\Dme\Models\LabOrder as AnalyseMedicale;
use Keneya\Dme\Models\MedicalDocument as DocumentMedical;
use Keneya\Dme\Models\Patient as DossierMedical;
use Keneya\Dme\Models\Prescription as OrdonnanceMedicale;

/**
 * L'identite permanente d'un patient.
 *
 * Depuis l'addendum v3, cette table ne porte plus ni service, ni ticket, ni
 * statut : tout cela appartient a un passage (App\Models\Visit). Le
 * `patient_code` est attribue une seule fois, a la toute premiere venue, et
 * ne change jamais.
 */
class Patient extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'patient_code',
        // `name` reste le nom complet affiche partout ; il est compose par
        // PatientObserver a partir des deux champs saisis, jamais l'inverse.
        'name',
        'first_name',
        'last_name',
        'age',
        'gender',
        'profession',
        'mobile',
        // Le seul champ qui distingue deux personnes a coup sur, et le
        // dernier recours de la recherche de doublon. Facultatif : un patient
        // arrive sans papiers doit pouvoir etre enregistre.
        'id_card_number',
        'crno',
        'note',
        'access_code',
        'portal_token',
    ];

    /**
     * Le code d'acces ne doit pas partir dans une serialisation par megarde.
     *
     * @var array<int, string>
     */
    protected $hidden = ['access_code'];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * Le passage le plus recent, quel que soit son statut.
     */
    public function latestVisit(): HasOne
    {
        return $this->hasOne(Visit::class)->latestOfMany('opened_at');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    public function companions(): HasMany
    {
        return $this->hasMany(Companion::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Ordonnances anterieures a la v3.3.1, restees dans la table de WorkFlow.
     *
     * Plus rien ne s'y ecrit : depuis la fusion des deux ordonnances, tout
     * part dans le dossier medical. La relation subsiste pour la suppression
     * d'un dossier, qui doit continuer d'emporter ces lignes.
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * Le dossier medical de ce patient, s'il en a un (v3.3.1).
     *
     * Nul tant qu'aucun acte n'a ete pose : le dossier nait au premier
     * formulaire du DME, jamais a l'enregistrement a l'accueil. Le lien passe
     * par la table d'identifiants externes du module, jamais par un
     * rapprochement sur le nom.
     */
    public function dossierMedical(): ?DossierMedical
    {
        return PatientProjection::find($this);
    }

    /**
     * Ses ordonnances, telles qu'elles vivent desormais : dans le dossier
     * medical, une seule table pour les deux interfaces.
     *
     * @return Collection<int, OrdonnanceMedicale>
     */
    public function ordonnances(): Collection
    {
        $dossier = $this->dossierMedical();

        if ($dossier === null) {
            return OrdonnanceMedicale::query()->whereRaw('1 = 0')->get();
        }

        return $dossier->prescriptions()
            ->with(['doctor', 'items'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Ses demandes d'analyses rendues (v3.4).
     *
     * Rendues, et rendues seulement : une demande encore au laboratoire n'a
     * rien a dire au patient, et l'afficher « en attente » ne ferait
     * qu'inquieter. `available` est l'etat qu'atteint une demande quand le
     * technicien a rendu sa conclusion, `validated` celui qu'elle atteint
     * apres validation biologique.
     *
     * @return Collection<int, AnalyseMedicale>
     */
    public function resultatsDAnalyse(): Collection
    {
        $dossier = $this->dossierMedical();

        if ($dossier === null) {
            return AnalyseMedicale::query()->whereRaw('1 = 0')->get();
        }

        return $dossier->labOrders()
            ->whereIn('status', ['available', 'validated'])
            ->with(['doctor', 'items.results'])
            ->get();
    }

    /**
     * Ses comptes rendus d'imagerie definitifs (v3.4).
     *
     * Un brouillon reste au dossier medical : le radiologue le reprend, le
     * corrige, et ce n'est qu'une fois signe qu'il devient la parole de
     * l'etablissement. Le portail ne montre que ce qui est arrete.
     *
     * @return Collection<int, CompteRenduImagerie>
     */
    public function comptesRendusDImagerie(): Collection
    {
        $dossier = $this->dossierMedical();

        if ($dossier === null) {
            return CompteRenduImagerie::query()->whereRaw('1 = 0')->get();
        }

        return $dossier->imagingReports()
            ->whereIn('status', ['final', 'amended'])
            ->with(['order', 'radiologist'])
            ->orderByDesc('reported_at')
            ->get();
    }

    /**
     * Les documents de son dossier medical (v3.4).
     *
     * A ne pas confondre avec `attachments`, les pieces jointes de WorkFlow,
     * qui accompagnent un renvoi et circulent avec le patient. Ici, ce qui a
     * vocation a rester au dossier : un compte rendu, un resultat scanne, un
     * certificat.
     *
     * Un brouillon n'y figure pas, un dossier archive non plus : l'un n'est
     * pas fini, l'autre a ete retire de la circulation.
     *
     * @return Collection<int, DocumentMedical>
     */
    public function documentsMedicaux(): Collection
    {
        $dossier = $this->dossierMedical();

        if ($dossier === null) {
            return DocumentMedical::query()->whereRaw('1 = 0')->get();
        }

        return $dossier->documents()
            ->whereIn('status', ['final', 'signed'])
            ->get();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function portalAccessAttempt(): HasOne
    {
        return $this->hasOne(PortalAccessAttempt::class);
    }

    /**
     * Identifiant du patient tel qu'on le prononce a l'accueil.
     */
    public function label(): string
    {
        return sprintf('%s (%s)', $this->name, $this->patient_code);
    }

    /**
     * Le code d'acces et le jeton du portail n'ont rien a faire dans un
     * journal consultable par l'administration.
     *
     * @return array<int, string>
     */
    protected function auditedAttributes(): array
    {
        return ['patient_code', 'name', 'first_name', 'last_name', 'age', 'gender', 'mobile', 'crno'];
    }

    public static function auditLabel(): string
    {
        return 'Patient';
    }
}
