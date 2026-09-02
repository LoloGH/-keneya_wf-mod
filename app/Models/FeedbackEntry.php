<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un retour : sondage de satisfaction, reclamation ou constat
 * (v3.2.8, point 4).
 */
class FeedbackEntry extends Model
{
    use HasFactory, RecordsActivity;

    /** Les notes du patient ou du visiteur, sollicitees par SMS. */
    public const TYPE_SURVEY = 'satisfaction_survey';

    /** Une reclamation, deposee par la personne concernee. */
    public const TYPE_COMPLAINT = 'complaint';

    /** Un constat, redige par un membre du personnel depuis son interface. */
    public const TYPE_INCIDENT = 'incident_report';

    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_SURVEY => 'Sondage de satisfaction',
        self::TYPE_COMPLAINT => 'Reclamation',
        self::TYPE_INCIDENT => 'Constat',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Nouveau',
        self::STATUS_REVIEWED => 'Examine',
        self::STATUS_RESOLVED => 'Resolu',
    ];

    protected $fillable = [
        'type',
        'patient_id',
        'visit_id',
        'visitor_id',
        'submitted_by_user_id',
        'handled_by_user_id',
        'service_id',
        'rating_care',
        'rating_staff',
        'content',
        'status',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_care' => 'integer',
            'rating_staff' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Les notes par etape du parcours (v3.2.9, point 3). Vides pour une
     * reclamation ou un constat.
     *
     * @return HasMany<FeedbackSurveyRating, $this>
     */
    public function surveyRatings(): HasMany
    {
        return $this->hasMany(FeedbackSurveyRating::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Le passage que ce retour concerne. Nul pour un constat general, ou pour
     * une reclamation deposee hors de tout passage.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Le sondage de ce passage a-t-il deja ete depose ?
     *
     * La regle vit ici, et non dans les deux formulaires : le portail patient
     * et la page du visiteur doivent la lire de la meme facon, et une action
     * qui ecrit doit pouvoir la verifier avant d'ecrire — un formulaire se
     * contourne, pas une regle de modele.
     */
    public static function sondageDejaDepose(?Visit $visite): bool
    {
        if (! $visite) {
            return false;
        }

        return self::query()
            ->where('type', self::TYPE_SURVEY)
            ->where('visit_id', $visite->getKey())
            ->exists();
    }

    /**
     * Meme question pour un visiteur. Pas besoin de colonne supplementaire :
     * chaque venue d'un visiteur cree son propre enregistrement, avec son
     * propre jeton de sondage. L'enregistrement EST la session.
     */
    public static function sondageDejaDeposeParVisiteur(?Visitor $visiteur): bool
    {
        if (! $visiteur) {
            return false;
        }

        return self::query()
            ->where('type', self::TYPE_SURVEY)
            ->where('visitor_id', $visiteur->getKey())
            ->exists();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Le membre du personnel ayant redige un constat. */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** La personne concernee par la note « personnel ». */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_RESOLVED);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Le nom de la personne a l'origine du retour, patient ou visiteur.
     * « Anonyme » plutot qu'un vide : un constat general n'en concerne aucun.
     */
    public function authorName(): string
    {
        return $this->patient?->name
            ?? $this->visitor?->name
            ?? $this->submittedBy?->name
            ?? 'Anonyme';
    }

    /** Un sondage porte des notes ; une reclamation ou un constat, jamais. */
    public function hasRatings(): bool
    {
        return $this->rating_care !== null || $this->rating_staff !== null;
    }

    public static function auditLabel(): string
    {
        return 'Retour';
    }
}
