<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recherche des dossiers deja ouverts pour la meme personne (v3.2.8, point 1).
 *
 * Rien ne verifiait, jusqu'ici, qu'un patient n'etait pas deja en base avant de
 * lui creer une identite : la meme personne pouvait repartir avec deux
 * `patient_code`, ce qui contredit la promesse centrale du produit, un
 * identifiant unique et permanent par patient.
 *
 * Trois passes, dans cet ordre de fiabilite decroissante :
 *  1. le numero de la carte d'identite (v3.3.1), le seul champ qui distingue
 *     deux personnes a coup sur. Il est facultatif, mais quand il est la, il
 *     tranche ;
 *  2. le numero de telephone : un nom se prononce et s'ecrit de dix facons, un
 *     numero ne se negocie pas. Mais un telephone se prete et se change ;
 *  3. le nom associe a un age voisin, pour rattraper le patient qui a change
 *     de numero depuis sa derniere venue.
 *
 * Aucune de ces passes ne bloque : elles proposent un dossier existant, la
 * receptionniste tranche. Un patient qui attend ne doit jamais se heurter a un
 * refus d'enregistrement.
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
    public function search(
        ?string $mobile,
        ?string $name = null,
        ?int $age = null,
        ?string $idCardNumber = null,
    ): Collection {
        $parCarte = $this->byIdCard($idCardNumber);

        if ($parCarte->isNotEmpty()) {
            return $parCarte;
        }

        $parTelephone = $this->byMobile($mobile);

        if ($parTelephone->isNotEmpty()) {
            return $parTelephone;
        }

        return $this->byNameAndAge($name, $age);
    }

    /**
     * Correspondance sur la carte d'identite, a la casse et aux espaces pres :
     * « AB 123 456 » et « ab123456 » designent la meme piece, et personne ne
     * saisit deux fois de la meme facon.
     *
     * @return Collection<int, Patient>
     */
    public function byIdCard(?string $idCardNumber): Collection
    {
        $normalise = $this->normaliseCarte($idCardNumber);

        // Trois caracteres au moins : en deca, ce n'est pas un numero de piece
        // mais une saisie interrompue, et la correspondance ramenerait tout.
        if (strlen($normalise) < 3) {
            return new Collection;
        }

        return Patient::query()
            ->whereNotNull('id_card_number')
            ->whereRaw('UPPER('.$this->expressionCarte().') = ?', [$normalise])
            ->orderBy('id')
            ->limit(self::LIMITE)
            ->get();
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

        return Patient::query()
            ->whereRaw($this->expressionChiffres().' LIKE ?', ['%'.$national])
            ->orderBy('id')
            ->limit(self::LIMITE)
            ->get();
    }

    /**
     * Seconde passe : meme nom, age voisin. Volontairement plus large que la
     * premiere : elle propose, elle ne tranche pas.
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

    /**
     * Retire de `patients.id_card_number` les separateurs de saisie, cote
     * base. Meme forme que pour le telephone, et pour la meme raison : c'est
     * la seule que MySQL/MariaDB et SQLite comprennent a l'identique.
     */
    private function expressionCarte(): string
    {
        $expression = 'id_card_number';

        foreach ([' ', '-', '.', '/'] as $caractere) {
            $expression = sprintf("REPLACE(%s, '%s', '')", $expression, $caractere);
        }

        return $expression;
    }

    private function normaliseCarte(?string $value): string
    {
        return mb_strtoupper(preg_replace('/[\s\-.\/]+/', '', (string) $value) ?? '');
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
