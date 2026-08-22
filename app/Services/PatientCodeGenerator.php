<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Generation des identifiants uniques et permanents du dossier patient
 * (ex. HFD-00001) et de la fiche visiteur (ex. HFD-V-00001).
 *
 * Le prefixe est celui de l'etablissement (config/keneya.php), afin que le
 * produit soit reutilisable dans d'autres hopitaux maliens.
 */
class PatientCodeGenerator
{
    public function forPatient(): string
    {
        return $this->next(Patient::class, 'patient_code', $this->prefix().'-');
    }

    public function forVisitor(): string
    {
        return $this->next(Visitor::class, 'visitor_code', $this->prefix().'-V-');
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function next(string $model, string $column, string $prefix): string
    {
        $query = $model::query()->where($column, 'like', $prefix.'%');

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        $last = $query->orderByDesc('id')->value($column);
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function prefix(): string
    {
        return (string) config('keneya.code_prefix', 'HFD');
    }
}
