<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_code',
        'name',
        'mobile',
        'service_id',
        'reason',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
