<?php

use App\Models\StaffType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prescrire un soin devient un acte attribuable (v3.3.1).
 *
 * La colonne `prescribed_by_doctor_id` n'admettait qu'un medecin, et le droit
 * de prescrire etait implicite : il venait avec « Hospitaliser un patient ».
 * Deux consequences, corrigees ici : un type de personnel dedie ne pouvait pas
 * prescrire du tout, et un etablissement ne pouvait pas dissocier les deux
 * actes, alors qu'admettre un patient et lui prescrire un traitement ne sont
 * pas la meme decision.
 *
 * Le motif est celui des renvois, de l'historique, des rendez-vous et des
 * admissions : une colonne par rattachement, l'une **ou** l'autre, jamais les
 * deux, et jamais aucune. On n'elargit pas `doctors` a tout le personnel : ce
 * serait fabriquer de faux medecins, avec les droits de l'interface /service.
 *
 * La capacite nouvelle est accordee d'office a tout type qui portait deja
 * l'hospitalisation : sans cela, chaque medecin de l'etablissement perdrait au
 * deploiement un bouton dont il se sert. Une migration ne retire jamais un
 * droit en cours d'usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_tasks', function (Blueprint $table) {
            $table->foreignId('prescribed_by_staff_member_id')->nullable()
                ->after('prescribed_by_doctor_id')->constrained('staff_members');
        });

        Schema::table('care_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('prescribed_by_doctor_id')->nullable()->change();
        });

        foreach (StaffType::all() as $type) {
            if (! $type->can(StaffType::CAP_ADMIT_HOSPITALIZATION)) {
                continue;
            }

            $capacites = $type->capabilities ?? [];

            if (in_array(StaffType::CAP_PRESCRIBE_CARE, $capacites, true)) {
                continue;
            }

            $capacites[] = StaffType::CAP_PRESCRIBE_CARE;

            // `saveQuietly` : une migration n'est pas un acte d'utilisateur et
            // n'a rien a ecrire au journal d'audit.
            $type->forceFill(['capabilities' => array_values($capacites)])->saveQuietly();
        }
    }

    public function down(): void
    {
        foreach (StaffType::all() as $type) {
            $capacites = $type->capabilities ?? [];

            if (! in_array(StaffType::CAP_PRESCRIBE_CARE, $capacites, true)) {
                continue;
            }

            $type->forceFill([
                'capabilities' => array_values(array_diff($capacites, [StaffType::CAP_PRESCRIBE_CARE])),
            ])->saveQuietly();
        }

        // Un soin prescrit par un personnel generique n'a pas d'equivalent
        // dans l'ancien schema : il est retire plutot que rattache a un
        // medecin invente.
        DB::table('care_tasks')->whereNull('prescribed_by_doctor_id')->delete();

        Schema::table('care_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescribed_by_staff_member_id');
        });

        Schema::table('care_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('prescribed_by_doctor_id')->nullable(false)->change();
        });
    }
};
