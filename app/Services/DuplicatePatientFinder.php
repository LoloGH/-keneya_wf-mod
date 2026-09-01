<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recherche des dossiers deja ouverts pour la meme personne (v3.2.8, point 1).
 *
 * Rien ne verifiait, jusqu'ici, qu'un patient n'etait pas deja en base avant de
 * lui creer une identite : la meme personne pouvait repartir avec deux
 * `patient_code`, ce qui contredit la promesse centrale du produit — un
 * identifiant unique et permanent par patient.
 *
 * Deux passes, dans cet ordre :
 *  1. le numero de telephone, le champ le plus fiable — un nom se prononce et
 *     s'ecrit de dix facons, un numero ne se negocie pas ;
 *  2. le nom associe a un age voisin, pour rattraper le patient qui a change
 *     de numero depuis sa derniere venue.
 */
class DuplicatePatientFinder
{
    /** Ecart d'age tolere par la seconde passe, en annees. */
    public const TOLERANCE_AGE = 2;

    /** Au-dela, la liste cesse d'aider a decider. */
    private const LIMITE = 5;

    /**
     * @return Collection<int, Patient>
     */
    public function search(?string $mobile, ?string $name = null, ?int $age = null): Collection
    {
        $parTelephone = $this->byMobile($mobile);

        if ($parTelephone->isNotEmpty()) {
            return $parTelephone;
        }

        return $this->byNameAndAge($name, $age);
    }

    /**
     * Correspondance exacte de numero, a la mise en forme pres : « 76 44 55 66 »,
     * « 76-44-55-66 » et « +223 76445566 » designent le meme telephone, et la
     * receptionniste ne saisit pas deux fois de la meme facon.
     *
     * @return Collection<int, Patient>
     */
    public function byMobile(?string $mobile): Collection
    {
        $chiffres = $this->digits($mobile);

        // Les numeros maliens tiennent en 8 chiffres ; on compare sur cette
        // longueur pour qu'un indicatif pays saisi d'un cote seulement
        // n'empeche pas la correspondance.
        if (strlen($chiffres) < 8) {
            return new Collection;
        }

        $national = substr($chiffres, -8);

        // CASSURE VOLONTAIRE — verification de la CI, annulee juste apres.
        return new Collection;
    }

    /**
     * Seconde passe : meme nom, age voisin. Volontairement plus large que la
     * premiere — elle propose, elle ne tranche pas.
     *
     * @return Collection<int, Patient>
     */
    public function byNameAndAge(?string $name, ?int $age): Collection
    {
        $name = trim((string) $name);

        if ($name === '' || $age === null) {
            return new Collection;
        }

        return Patient::query()
            ->whereRaw('LOWER(name) = LOWER(?)', [$this->normaliseEspaces($name)])
            ->whereBetween('age', [$age - self::TOLERANCE_AGE, $age + self::TOLERANCE_AGE])
            ->orderBy('id')
            ->limit(self::LIMITE)
            ->get();
    }

    /**
     * Retire de `patients.mobile` tout ce qui n'est pas un chiffre, cote base.
     *
     * Ecrit en REPLACE imbriques plutot qu'avec une fonction de nettoyage :
     * c'est la seule forme que MySQL/MariaDB et SQLite comprennent tous les
     * deux a l'identique, et la suite de tests tourne sur les deux.
     */
    private function expressionChiffres(): string
    {
        $expression = 'mobile';

        foreach ([' ', '-', '.', '+', '(', ')', '/'] as $caractere) {
            $expression = sprintf("REPLACE(%s, '%s', '')", $expression, $caractere);
        }

        return $expression;
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normaliseEspaces(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }
}
