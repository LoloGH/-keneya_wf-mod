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
 *
 * **Series (v3.2.6).** La premiere serie va de 00001 a 99999. Au-dela, une
 * lettre prend le relais : A0001 a A9999, puis B0001, et ainsi de suite
 * jusqu'a Z9999. Cela porte la capacite a un peu plus de 350 000 dossiers en
 * gardant un numero court, dictable au telephone et lisible sur un ticket
 * thermique.
 *
 * Un numero est attribue a vie : aucune serie n'est jamais reprise, meme si
 * des dossiers sont supprimes.
 */
class PatientCodeGenerator
{
    /** Longueur du rang dans la premiere serie, sans lettre. */
    private const RANG_NUMERIQUE = 5;

    /** Longueur du rang dans les series a lettre. */
    private const RANG_LETTRE = 4;

    /** Dernier rang d'une serie a lettre avant de passer a la suivante. */
    private const RANG_MAX_LETTRE = 9999;

    /** Dernier rang de la serie numerique. */
    private const RANG_MAX_NUMERIQUE = 99999;

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

        // Le tri par `id` ne suffit plus : un dossier de la serie A peut avoir
        // ete cree avant une reprise de la serie numerique. C'est le code
        // lui-meme qui porte l'ordre, et il faut donc les comparer tous.
        $dernier = $query->pluck($column)
            ->map(fn (string $code) => substr($code, strlen($prefix)))
            ->sort(fn (string $a, string $b) => $this->rangGlobal($a) <=> $this->rangGlobal($b))
            ->last();

        return $prefix.$this->suivant($dernier);
    }

    /**
     * Le suffixe qui suit celui donne, ou le premier de tous si la table est
     * vide.
     */
    private function suivant(?string $suffixe): string
    {
        if ($suffixe === null || $suffixe === '') {
            return str_pad('1', self::RANG_NUMERIQUE, '0', STR_PAD_LEFT);
        }

        [$lettre, $rang] = $this->decoupe($suffixe);

        // Fin de la serie numerique : on ouvre la serie A.
        if ($lettre === null) {
            return $rang >= self::RANG_MAX_NUMERIQUE
                ? 'A'.str_pad('1', self::RANG_LETTRE, '0', STR_PAD_LEFT)
                : str_pad((string) ($rang + 1), self::RANG_NUMERIQUE, '0', STR_PAD_LEFT);
        }

        if ($rang < self::RANG_MAX_LETTRE) {
            return $lettre.str_pad((string) ($rang + 1), self::RANG_LETTRE, '0', STR_PAD_LEFT);
        }

        // Fin d'une serie a lettre : on passe a la lettre suivante. Apres Z,
        // il n'y a plus de place : mieux vaut s'arreter net que de fabriquer
        // un numero ambigu qui pourrait doublonner un dossier existant.
        if ($lettre === 'Z') {
            throw new \RuntimeException(
                'La derniere serie de numeros de dossier (Z9999) est atteinte. '
                .'Choisissez un nouveau prefixe d\'etablissement avant de continuer.'
            );
        }

        return chr(ord($lettre) + 1).str_pad('1', self::RANG_LETTRE, '0', STR_PAD_LEFT);
    }

    /**
     * Rang absolu d'un suffixe, toutes series confondues : c'est lui qui
     * permet de dire que A0001 vient apres 99999.
     */
    private function rangGlobal(string $suffixe): int
    {
        [$lettre, $rang] = $this->decoupe($suffixe);

        if ($lettre === null) {
            return $rang;
        }

        return self::RANG_MAX_NUMERIQUE
            + (ord($lettre) - ord('A')) * self::RANG_MAX_LETTRE
            + $rang;
    }

    /**
     * @return array{0: ?string, 1: int} la lettre de serie (nulle pour la
     *                                   premiere) et le rang dans cette serie
     */
    private function decoupe(string $suffixe): array
    {
        $suffixe = strtoupper(trim($suffixe));

        return preg_match('/^([A-Z])(\d+)$/', $suffixe, $trouve) === 1
            ? [$trouve[1], (int) $trouve[2]]
            : [null, (int) $suffixe];
    }

    private function prefix(): string
    {
        return (string) config('keneya.code_prefix', 'HFD');
    }
}
