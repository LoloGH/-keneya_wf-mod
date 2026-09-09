<?php

use App\Models\Service;
use App\Models\ServiceKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'accueil devient un service (v3.2.5).
 *
 * Les caisses etaient deja des services a part entiere : elles ont des heures,
 * du personnel, une file. L'accueil est exactement dans ce cas, mais n'existait
 * nulle part, ce qui avait deux consequences visibles :
 *
 *  - il ne figurait ni dans « Liste des services » ni dans le menu « Service »
 *    des plannings ;
 *  - une receptionniste n'ayant aucun service de rattachement, son creneau ne
 *    pouvait designer aucun service, donc elle n'etait jamais « de garde »,
 *    donc elle ne recevait aucune notification.
 *
 * Ce n'est pas une destination de soins : voir ServiceKind::NON_CARE_SLUGS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kindId = DB::table('service_kinds')->where('slug', ServiceKind::SLUG_RECEPTION)->value('id');

        if (! $kindId) {
            $kindId = DB::table('service_kinds')->insertGetId([
                'name' => 'Accueil',
                'slug' => ServiceKind::SLUG_RECEPTION,
                // Aucun peage : on ne paie pas pour se presenter a l'accueil.
                'requires_payment_gate' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Idempotent : une installation qui aurait deja cree son propre
        // « Accueil » a la main n'en recoit pas un second.
        if (! DB::table('services')->where('name', Service::RECEPTION)->exists()) {
            DB::table('services')->insert([
                'name' => Service::RECEPTION,
                'service_kind_id' => $kindId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Les creneaux poses sur l'accueil partent avec lui : un planning qui
        // designe un service disparu bloquerait la contrainte de cle etrangere.
        $serviceIds = DB::table('services')
            ->join('service_kinds', 'service_kinds.id', '=', 'services.service_kind_id')
            ->where('service_kinds.slug', ServiceKind::SLUG_RECEPTION)
            ->pluck('services.id');

        if ($serviceIds->isNotEmpty()) {
            DB::table('schedules')->whereIn('service_id', $serviceIds)->delete();
            DB::table('services')->whereIn('id', $serviceIds)->delete();
        }

        DB::table('service_kinds')->where('slug', ServiceKind::SLUG_RECEPTION)->delete();
    }
};
