<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'service_id', 'phone'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function sentReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'from_doctor_id');
    }

    public function completedReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'completed_by_doctor_id');
    }

    public function name(): string
    {
        return $this->user?->name ?? '';
    }
}
