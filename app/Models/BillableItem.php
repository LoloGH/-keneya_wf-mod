<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un acte facturable et son tarif (v3.2.8, point 3).
 *
 * « Echographie abdominale », et non « Echographie » : c'est l'acte precis qui
 * porte un prix, et c'est lui que le patient doit lire sur son recu.
 */
class BillableItem extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['name', 'service_id', 'price'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * Les actes proposables pour un service : les siens, plus les tarifs
     * generiques qui ne dependent d'aucun service.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForService(Builder $query, ?int $serviceId): void
    {
        $query->where(function (Builder $query) use ($serviceId): void {
            $query->whereNull('service_id');

            if ($serviceId) {
                $query->orWhere('service_id', $serviceId);
            }
        });
    }

    /** « 2 500 FCFA », a la maniere malienne. */
    public function formattedPrice(): string
    {
        return number_format((float) $this->price, 0, ',', ' ').' FCFA';
    }

    public function label(): string
    {
        return sprintf('%s — %s', $this->name, $this->formattedPrice());
    }

    public static function auditLabel(): string
    {
        return 'Tarif';
    }
}
