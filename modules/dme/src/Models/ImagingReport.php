<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Compte rendu d'imagerie (§24). */
class ImagingReport extends Model
{
    protected $table = 'dme_imaging_reports';

    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'imaging_order_id', 'patient_id', 'radiologist_id', 'technique',
        'findings', 'conclusion', 'is_abnormal', 'reported_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'is_abnormal' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ImagingOrder::class, 'imaging_order_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function radiologist(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'radiologist_id');
    }

    public function auditLabel(): string
    {
        return 'Compte rendu d\'imagerie #'.$this->getKey();
    }
}
