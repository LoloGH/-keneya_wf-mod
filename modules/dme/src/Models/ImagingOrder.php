<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Keneya\Dme\Dme;
use Keneya\Dme\Models\Concerns\HasBusinessIdentifier;
use Keneya\Dme\Models\Concerns\RecordsMedicalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Demande d'imagerie (§24). Correspondance FHIR : ImagingStudy (§44).
 *
 * Préparation DICOM/PACS (§46) : `accession_number` et
 * `study_instance_uid` sont les clés d'accroche d'une future passerelle.
 * Cette version ne stocke que des métadonnées et des documents.
 */
class ImagingOrder extends Model
{
    protected $table = 'dme_imaging_orders';

    use HasBusinessIdentifier;
    use HasFactory;
    use RecordsMedicalActivity;

    protected $fillable = [
        'order_number', 'patient_id', 'consultation_id', 'doctor_id',
        'modality', 'body_site', 'requested_at', 'scheduled_for', 'priority',
        'indication', 'status', 'accession_number', 'study_instance_uid',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_for' => 'datetime',
        ];
    }

    /** @var array<string, string> */
    public const MODALITIES = [
        'radiography' => 'Radiographie',
        'ultrasound' => 'Échographie',
        'ct' => 'Scanner',
        'mri' => 'IRM',
        'mammography' => 'Mammographie',
        'other' => 'Autre',
    ];

    /** @var array<string, string> */
    public const STATUSES = [
        'requested' => 'Demandé',
        'scheduled' => 'Programmé',
        'performed' => 'Réalisé',
        'reported' => 'Compte rendu disponible',
        'cancelled' => 'Annulé',
    ];

    public function identifierPrefixKey(): string
    {
        return 'imaging_order';
    }

    public function identifierColumn(): string
    {
        return 'order_number';
    }

    public function auditLabel(): string
    {
        return 'Imagerie '.$this->order_number;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Dme::userModel(), 'doctor_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(ImagingReport::class);
    }

    public function modalityLabel(): string
    {
        return self::MODALITIES[$this->modality] ?? $this->modality;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
