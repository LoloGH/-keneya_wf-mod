<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une pathologie, au sens du regroupement de patients (v3.2.9, point 1).
 *
 * Ce n'est pas un diagnostic code : c'est une etiquette posee sur une visite
 * pour pouvoir, plus tard, s'adresser a un groupe, « les patients suivis pour
 * de l'hypertension ». Un vrai codage medical releverait d'un autre travail.
 */
class Pathology extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['name'];

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public static function auditLabel(): string
    {
        return 'Pathologie';
    }
}
