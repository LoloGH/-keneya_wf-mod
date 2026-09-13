<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit (§30).
 *
 * La table conserve le schéma attendu par spatie/laravel-activitylog
 * (log_name, description, subject, causer, properties) et y ajoute les
 * colonnes exigées par la spécification : rôle, patient concerné, adresse
 * IP et résultat de l'action. Un seul journal est donc maintenu : les
 * modifications de modèles et les accès aux dossiers y cohabitent, ce qui
 * évite deux systèmes d'audit parallèles.
 *
 * `activity_log` est l'une des rares tables que le module partage avec son
 * hôte au lieu de la préfixer : c'est ce partage, et lui seul, qui fait
 * qu'une action posée dans le dossier médical apparaît dans le journal
 * d'audit de l'application hôte plutôt que dans un second registre
 * parallèle. La migration est donc additive : si l'hôte a déjà sa table,
 * on n'y ajoute que les colonnes médicales qui lui manquent.
 *
 * Append-only : aucune route ni policy n'autorise la modification ou la
 * suppression d'une entrée. Le modèle AuditLog bloque également ces
 * opérations au niveau applicatif.
 */
return new class extends Migration
{
    /**
     * Colonnes propres au contexte médical Keneya-DME.
     *
     * @var array<int, string>
     */
    private const MEDICAL_COLUMNS = [
        'causer_role', 'patient_id', 'action', 'outcome',
        'ip_address', 'user_agent', 'route',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            $this->createBaseTable();
        }

        $this->addMedicalColumns();
    }

    /**
     * Socle spatie/laravel-activitylog, créé seulement quand le module
     * tourne seul : chez un hôte, cette table existe déjà.
     */
    private function createBaseTable(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->string('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Ajout colonne par colonne : une table héritée de l'hôte peut déjà en
     * porter certaines, et une migration qui suppose l'inverse échouerait
     * au milieu en laissant la table à moitié enrichie.
     */
    private function addMedicalColumns(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // `event` et `batch_uuid` sont apparus dans activitylog v4 :
            // un hôte installé plus tôt peut ne pas les avoir.
            if (! Schema::hasColumn('activity_log', 'event')) {
                $table->string('event')->nullable();
            }

            if (! Schema::hasColumn('activity_log', 'batch_uuid')) {
                $table->string('batch_uuid')->nullable();
            }

            if (! Schema::hasColumn('activity_log', 'causer_role')) {
                $table->string('causer_role')->nullable();
            }

            if (! Schema::hasColumn('activity_log', 'patient_id')) {
                $table->unsignedBigInteger('patient_id')->nullable();
                $table->index('patient_id');   // §59
            }

            if (! Schema::hasColumn('activity_log', 'action')) {
                // viewed, created, updated, downloaded, denied...
                $table->string('action')->nullable();
                $table->index('action');
            }

            if (! Schema::hasColumn('activity_log', 'outcome')) {
                $table->enum('outcome', ['allowed', 'denied', 'failed'])->default('allowed');
                $table->index('outcome');
            }

            if (! Schema::hasColumn('activity_log', 'ip_address')) {
                $table->string('ip_address', 45)->nullable();
            }

            if (! Schema::hasColumn('activity_log', 'user_agent')) {
                $table->text('user_agent')->nullable();
            }

            if (! Schema::hasColumn('activity_log', 'route')) {
                $table->string('route')->nullable();
            }
        });
    }

    /**
     * Seules les colonnes médicales sont retirées : la table appartient
     * peut-être à l'hôte, et la supprimer effacerait son journal.
     */
    public function down(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            foreach (self::MEDICAL_COLUMNS as $column) {
                if (Schema::hasColumn('activity_log', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
