<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Dme\PatientProjection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'name',
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
        return ['patient_code', 'name', 'age', 'gender', 'mobile', 'crno'];
    }

    public static function auditLabel(): string
    {
        return 'Patient';
    }
}
