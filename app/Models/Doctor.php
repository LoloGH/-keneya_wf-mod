<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Doctor extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['user_id', 'service_id', 'phone', 'signature_path', 'stamp_path'];

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

    public static function auditLabel(): string
    {
        return 'Medecin';
    }

    /**
     * Chemin absolu de la signature, pour dompdf : nul si le fichier n'est
     * pas la (v3.2.9, point 2).
     *
     * C'est ce controle d'existence qui tient la promesse « une ordonnance
     * s'imprime meme sans signature » : un chemin en base dont le fichier a
     * disparu ferait echouer la generation, et une ordonnance qu'on ne peut
     * plus imprimer est un probleme bien plus grave qu'une signature manquante.
     */
    public function signatureFile(): ?string
    {
        return self::fichierExistant($this->signature_path);
    }

    public function stampFile(): ?string
    {
        return self::fichierExistant($this->stamp_path);
    }

    /** Le chemin absolu si, et seulement si, le fichier est reellement lisible. */
    public static function fichierExistant(?string $chemin): ?string
    {
        if (blank($chemin)) {
            return null;
        }

        $disque = Storage::disk('signatures');

        return $disque->exists($chemin) ? $disque->path($chemin) : null;
    }
}
