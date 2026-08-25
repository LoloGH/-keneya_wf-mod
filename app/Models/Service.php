<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, RecordsActivity;

    public const KIND_CLINIQUE = 'clinique';

    public const KIND_PLATEAU_TECHNIQUE = 'plateau_technique';

    public const KIND_CAISSE = 'caisse';

    /** Nom des deux caisses, referencees par le routage sous condition de paiement. */
    public const CAISSE_TICKET = 'Caisse Ticket';

    public const CAISSE_SERVICES = 'Caisse Services';

    /**
     * Libelles francais des types de service, pour l'affichage.
     *
     * @var array<string, string>
     */
    public const KIND_LABELS = [
        self::KIND_CLINIQUE => 'Clinique',
        self::KIND_PLATEAU_TECHNIQUE => 'Plateau technique',
        self::KIND_CAISSE => 'Caisse',
    ];

    protected $fillable = ['name', 'kind'];

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function incomingReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'to_service_id');
    }

    public function outgoingReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'from_service_id');
    }

    /**
     * Les services vers lesquels on oriente reellement un patient.
     *
     * La caisse en est exclue : elle est une etape de routage decidee par
     * RouteThroughCaisse, jamais une destination qu'on choisit dans un
     * formulaire. C'est aussi ce qui empeche d'affecter un medecin a une
     * caisse — les medecins n'encaissent jamais.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCareServices(Builder $query): void
    {
        $query->where('kind', '!=', self::KIND_CAISSE);
    }

    public function isCaisse(): bool
    {
        return $this->kind === self::KIND_CAISSE;
    }

    public function isPlateauTechnique(): bool
    {
        return $this->kind === self::KIND_PLATEAU_TECHNIQUE;
    }

    public function isClinique(): bool
    {
        return $this->kind === self::KIND_CLINIQUE;
    }

    /**
     * La caisse par laquelle passe un patient avant d'atteindre ce service :
     * « Caisse Ticket » pour une consultation, « Caisse Services » pour un acte.
     */
    public static function caisseFor(self $destination): ?self
    {
        $nom = $destination->isPlateauTechnique() ? self::CAISSE_SERVICES : self::CAISSE_TICKET;

        return static::where('kind', self::KIND_CAISSE)->where('name', $nom)->first();
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }

    public static function auditLabel(): string
    {
        return 'Service';
    }
}
