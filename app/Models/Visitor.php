<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visitor extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'patient_id',
        'visitor_code',
        'name',
        'mobile',
        'service_id',
        'token',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'integer',
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

    public static function auditLabel(): string
    {
        return 'Visiteur';
    }
}
