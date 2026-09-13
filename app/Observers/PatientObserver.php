<?php

namespace App\Observers;

use App\Models\Patient;
use App\Services\PatientCodeGenerator;
use Illuminate\Support\Str;

class PatientObserver
{
    public function __construct(private readonly PatientCodeGenerator $codes) {}

    /**
     * Le patient_code, le code d'acces et le jeton du portail sont attribues
     * ici, et nulle part ailleurs : quel que soit le point d'entree
     * (formulaire, seeder, import, tinker), un patient ne peut pas exister
     * sans identifiant unique ni sans moyen d'acceder a ses documents.
     */
    public function creating(Patient $patient): void
    {
        if (blank($patient->patient_code)) {
            $patient->patient_code = $this->codes->forPatient();
        }

        if (blank($patient->access_code)) {
            // Quatre chiffres, zeros de tete conserves : « 0421 » est valide.
            $patient->access_code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        if (blank($patient->portal_token)) {
            // Un UUID, jamais le patient_code ni l'id : l'URL du portail ne
            // doit pas se deviner de proche en proche.
            $patient->portal_token = (string) Str::uuid();
        }
    }

    /**
     * Nom, prenom et nom complet ne divergent jamais.
     *
     * L'accueil saisit deux champs depuis la v3.3.2, mais `name` reste ce que
     * lisent le ticket, le SMS, la recherche et le journal d'audit. Le
     * composer ici, plutot que dans le formulaire, garantit qu'un patient cree
     * par un seeder, un import ou tinker porte la meme identite qu'un patient
     * cree a l'accueil.
     *
     * L'inverse tient aussi : un appelant qui ne connait que le nom complet
     * (une fabrique de test, une reprise de donnees) obtient un decoupage par
     * defaut plutot que deux colonnes vides. Le premier mot est le prenom, le
     * reste le nom de famille — l'ordre dans lequel une identite s'ecrit ici.
     */
    public function saving(Patient $patient): void
    {
        $prenom = trim((string) $patient->first_name);
        $nom = trim((string) $patient->last_name);

        if ($prenom === '' && $nom === '') {
            [$prenom, $nom] = self::decouper((string) $patient->name);
        }

        $patient->first_name = $prenom;
        $patient->last_name = $nom;
        $patient->name = trim($prenom.' '.$nom);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function decouper(string $complet): array
    {
        $morceaux = preg_split('/\s+/', trim($complet), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($morceaux) <= 1) {
            return ['', implode(' ', $morceaux)];
        }

        return [(string) array_shift($morceaux), implode(' ', $morceaux)];
    }
}
