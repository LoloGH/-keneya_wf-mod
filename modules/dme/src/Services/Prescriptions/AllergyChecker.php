<?php

declare(strict_types=1);

namespace Keneya\Dme\Services\Prescriptions;

use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Contrôle d'allergie avant validation d'une ordonnance (§22).
 *
 * Comportement voulu, et volontairement conservateur :
 *   - on recherche une correspondance entre les allergènes connus actifs
 *     du patient et les médicaments prescrits ;
 *   - on avertit l'utilisateur et on trace l'avertissement ;
 *   - on ne supprime JAMAIS automatiquement une ligne de prescription.
 *
 * La décision reste au prescripteur : un médecin peut légitimement
 * prescrire malgré une allergie documentée (désensibilisation, absence
 * d'alternative, allergie invalidée cliniquement). Le refus automatique
 * serait un risque en soi.
 *
 * Limite assumée de la phase 1 : la correspondance est textuelle
 * (allergène ↔ nom du médicament, avec quelques familles usuelles). Elle
 * ne remplace pas une base médicamenteuse type Thériaque/ANSM, dont
 * l'intégration est un point de la feuille de route.
 */
class AllergyChecker
{
    /**
     * Familles médicamenteuses usuelles : un allergène connu implique une
     * vigilance sur les molécules apparentées.
     *
     * @var array<string, list<string>>
     */
    private const FAMILIES = [
        'penicilline' => ['amoxicilline', 'ampicilline', 'oxacilline', 'penicilline', 'augmentin'],
        'sulfamide' => ['sulfamethoxazole', 'cotrimoxazole', 'bactrim', 'sulfadiazine'],
        'ains' => ['ibuprofene', 'diclofenac', 'ketoprofene', 'naproxene', 'aspirine'],
        'cephalosporine' => ['cefixime', 'ceftriaxone', 'cefalexine', 'cefuroxime'],
        'quinolone' => ['ciprofloxacine', 'levofloxacine', 'ofloxacine'],
        'iode' => ['iode', 'povidone', 'produit de contraste'],
    ];

    /**
     * Confronte les lignes d'une ordonnance aux allergies du patient.
     *
     * @return list<array{allergen: string, severity: string, severity_label: string, medication: string, reason: string}>
     */
    public function check(Prescription $prescription): array
    {
        $prescription->loadMissing(['items', 'patient.allergies']);

        $medications = $prescription->items
            ->pluck('medication_name')
            ->filter()
            ->values();

        return $this->match($prescription->patient, $medications);
    }

    /**
     * Confronte une liste de médicaments aux allergies actives d'un patient.
     *
     * @param  Collection<int, string>|list<string>  $medications
     * @return list<array{allergen: string, severity: string, severity_label: string, medication: string, reason: string}>
     */
    public function match(Patient $patient, Collection|array $medications): array
    {
        $medications = collect($medications)->filter()->values();

        $allergies = $patient->relationLoaded('allergies')
            ? $patient->allergies->where('status', 'active')
            : $patient->allergies()->active()->get();

        $warnings = [];

        foreach ($allergies as $allergy) {
            foreach ($medications as $medication) {
                $reason = $this->reasonFor($allergy, (string) $medication);

                if ($reason !== null) {
                    $warnings[] = [
                        'allergen' => $allergy->allergen,
                        'severity' => $allergy->severity,
                        'severity_label' => $allergy->severityLabel(),
                        'medication' => (string) $medication,
                        'reason' => $reason,
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Retourne le motif de l'alerte, ou null si aucune correspondance.
     */
    private function reasonFor(Allergy $allergy, string $medication): ?string
    {
        $allergen = $this->normalize($allergy->allergen);
        $drug = $this->normalize($medication);

        if ($allergen === '' || $drug === '') {
            return null;
        }

        // Correspondance directe sur le nom
        if (str_contains($drug, $allergen) || str_contains($allergen, $drug)) {
            return 'Correspondance directe avec un allergène documenté.';
        }

        // Correspondance par famille médicamenteuse
        foreach (self::FAMILIES as $family => $molecules) {
            $allergenInFamily = str_contains($allergen, $family)
                || $this->containsAny($allergen, $molecules);

            if (! $allergenInFamily) {
                continue;
            }

            if (str_contains($drug, $family) || $this->containsAny($drug, $molecules)) {
                return 'Molécule apparentée à la famille « '.$family.' ».';
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $this->normalize($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minuscules sans accents ni ponctuation, pour comparer « Pénicilline »
     * et « penicilline » de la même manière.
     */
    private function normalize(string $value): string
    {
        // Str::ascii plutôt qu'iconv//TRANSLIT : la translittération
        // d'iconv dépend de la bibliothèque C du système et ne rend pas le
        // même résultat partout, ce qui ferait dependre une alerte
        // d'allergie de l'image PHP utilisée.
        $value = Str::lower(Str::ascii(trim($value)));

        // On ne garde que le premier terme significatif (« amoxicilline 500 mg » -> « amoxicilline »)
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        $first = explode(' ', $value)[0] ?? $value;

        return mb_strlen($first) >= 4 ? $first : $value;
    }
}
