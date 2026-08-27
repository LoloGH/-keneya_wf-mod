<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une note de releve laissee sur un sejour (v3.2.3, point 4).
 *
 * Pas de champ « lu par », pas d'accuse de reception : une note visible suffit.
 * Exiger une lecture confirmee ajouterait une file de plus a traiter, pour un
 * besoin que l'usage n'a pas encore montre.
 */
class HandoffNote extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['hospitalization_id', 'written_by_user_id', 'content'];

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function writtenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'written_by_user_id');
    }

    public static function auditLabel(): string
    {
        return 'Note de releve';
    }
}
