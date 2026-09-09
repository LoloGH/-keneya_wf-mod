<?php

use App\Models\StaffType;
use App\Support\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capacites pour tous les types de personnel, y compris ceux adosses a un role
 * (v3.2.2).
 *
 * Jusqu'ici, un type adosse a `doctor`, `receptionist` ou `cashier` n'avait
 * aucune capacite : les quatre interfaces etaient figees. L'etablissement veut
 * pouvoir moduler ce que fait un medecin, prescrire, donner un rendez-vous,
 * hospitaliser, sans toucher au code.
 *
 * Deux precautions pour que la demo et le site en production ne bougent pas :
 *
 *  - les types d'origine recoivent **toutes** leurs capacites optionnelles
 *    activees. Une interface qui perdrait des sections apres une simple
 *    migration serait une regression invisible ;
 *  - chaque compte existant est rattache au type de son role, pour que la
 *    resolution ne depende pas d'un choix que personne n'a encore fait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nul = on retombe sur le type du role, voir User::staffType().
            $table->foreignId('staff_type_id')->nullable()->after('password')->constrained();
        });

        foreach (StaffType::ROLE_CAPABILITIES as $role => $capacites) {
            $toutes = array_values(array_unique(array_merge($capacites['required'], $capacites['optional'])));

            DB::table('staff_types')
                ->where('matched_role', $role)
                ->update(['capabilities' => json_encode($toutes)]);
        }

        $this->attachExistingAccounts();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_type_id');
        });

        // Les types adosses a un role redeviennent sans capacites, comme avant.
        DB::table('staff_types')->whereNotNull('matched_role')->update(['capabilities' => null]);
    }

    /**
     * Rattache medecins, receptionnistes et caissiers au type de leur role.
     *
     * On vise le type d'origine : le premier cree, celui que la migration du
     * v3.2.1 a pose, et non un homonyme ajoute depuis par l'admin.
     */
    private function attachExistingAccounts(): void
    {
        $types = [];

        foreach ([Roles::DOCTOR, Roles::RECEPTIONIST, Roles::CASHIER] as $role) {
            $types[$role] = DB::table('staff_types')
                ->where('matched_role', $role)
                ->orderBy('id')
                ->value('id');
        }

        foreach ([
            'doctors' => Roles::DOCTOR,
            'receptionists' => Roles::RECEPTIONIST,
            'cashiers' => Roles::CASHIER,
        ] as $table => $role) {
            if (! $types[$role] || ! Schema::hasTable($table)) {
                continue;
            }

            DB::table('users')
                ->whereIn('id', DB::table($table)->select('user_id'))
                ->whereNull('staff_type_id')
                ->update(['staff_type_id' => $types[$role]]);
        }
    }
};
