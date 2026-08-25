<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Creneau de travail d'un membre du personnel.
 */
class Schedule extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['user_id', 'date', 'start_time', 'end_time', 'service_id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * « 08:00 – 14:00 », sans les secondes stockees en base.
     */
    public function range(): string
    {
        return sprintf('%s – %s', substr((string) $this->start_time, 0, 5), substr((string) $this->end_time, 0, 5));
    }

    public static function auditLabel(): string
    {
        return 'Creneau';
    }
}
