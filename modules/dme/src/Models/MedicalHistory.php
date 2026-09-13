<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antécédent médical (§16) : personnel, chirurgical, familial,
 * gynéco-obstétrique ou facteur de risque.
 */
class MedicalHistory extends Model
{
    use HasFactory;
    use RecordsMedicalActivity;

    protected $table = 'dme_medical_histories';

    protected $fillable = [
        'patient_id', 'category', 'label', 'code', 'code_system', 'year',
        'occurred_on', 'facility', 'relative', 'complications', 'comment',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }

    /** @var array<string, string> */
    public const CATEGORIES = [
        'personal' => 'Personnels',
        'surgical' => 'Chirurgicaux',
        'family' => 'Familiaux',
        'gynecological' => 'Gynéco-obstétriques',
        'risk_factor' => 'Facteurs de risque',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'recorded_by');
    }

    public function auditLabel(): string
    {
        return 'Antécédent '.mb_strtolower($this->categoryLabel()).' - '.$this->label;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
