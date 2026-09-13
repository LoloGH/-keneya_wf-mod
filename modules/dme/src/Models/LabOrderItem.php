<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Examen demandé au sein d'une demande de laboratoire (§23). */
class LabOrderItem extends Model
{
    protected $table = 'dme_lab_order_items';

    use HasFactory;

    protected $fillable = [
        'lab_order_id', 'exam_name', 'exam_code', 'category', 'status',
    ];

    public function labOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }
}
