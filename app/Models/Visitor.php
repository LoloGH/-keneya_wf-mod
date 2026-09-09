<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'patient_id',
        'visitor_code',
        'name',
        'mobile',
        // Un accompagnateur revient : l'etablissement doit pouvoir le
        // reconnaitre. Facultatif, comme pour un patient.
        'id_card_number',
        'service_id',
        'registered_by_user_id',
        'token',
        'feedback_token',
        'reason',
        'feedback_link_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'integer',
            'feedback_link_sent_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Le patient a qui l'on rend visite. Nul pour une demarche administrative.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * L'agent d'accueil qui a recu ce visiteur (v3.2.8, point 4) : c'est la
     * personne que sa note « personnel » concerne.
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    /** @return HasMany<FeedbackEntry, $this> */
    public function feedbackEntries(): HasMany
    {
        return $this->hasMany(FeedbackEntry::class);
    }

    /** Le lien de retour lui a-t-il deja ete propose ? */
    public function feedbackLinkSent(): bool
    {
        return $this->feedback_link_sent_at !== null;
    }

    public static function auditLabel(): string
    {
        return 'Visiteur';
    }
}
