<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = ['patient_id', 'visit_id', 'doctor_id', 'lines', 'content'];

    protected function casts(): array
    {
        return ['lines' => 'array'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Les lignes de l'ordonnance, quelle que soit sa forme (v3.2.6).
     *
     * Une ordonnance ecrite depuis la v3.2.6 porte ses lignes ; une plus
     * ancienne porte un texte libre, dont chaque ligne non vide devient une
     * ligne d'ordonnance sans posologie ni duree distinctes. L'affichage et
     * l'impression n'ont ainsi qu'un seul chemin a connaitre.
     *
     * @return array<int, array{medicament: string, posologie: ?string, duree: ?string}>
     */
    public function lignes(): array
    {
        if (filled($this->lines)) {
            return array_values(array_map(fn (array $ligne) => [
                'medicament' => (string) ($ligne['medicament'] ?? ''),
                'posologie' => $ligne['posologie'] ?? null,
                'duree' => $ligne['duree'] ?? null,
            ], $this->lines));
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) $this->content) ?: [])
            ->map(fn (string $ligne) => trim($ligne))
            ->filter()
            ->map(fn (string $ligne) => ['medicament' => $ligne, 'posologie' => null, 'duree' => null])
            ->values()
            ->all();
    }

    /**
     * Rendu d'une ligne sur une seule phrase, pour les endroits qui n'ont pas
     * de place pour un tableau (frise du dossier, portail patient).
     *
     * @param  array{medicament: string, posologie: ?string, duree: ?string}  $ligne
     */
    public static function ligneEnTexte(array $ligne): string
    {
        return collect([$ligne['medicament'], $ligne['posologie'], $ligne['duree']])
            ->filter(fn (?string $part) => filled($part))
            ->implode('-');
    }

    public static function auditLabel(): string
    {
        return 'Ordonnance';
    }
}
