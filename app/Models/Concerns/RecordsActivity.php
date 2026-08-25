<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Journalisation automatique des ecritures d'un modele (v3.2, point 8).
 *
 * Couvre les operations CRUD ordinaires. Les actes metier qui ne se resument
 * pas a un diff d'attributs — appeler le suivant, confirmer un paiement,
 * cloturer un dossier — sont journalises explicitement, en francais, par les
 * Actions concernees : un diff d'attributs est illisible pour qui relit
 * l'audit six mois plus tard.
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(Audit::LOG_NAME)
            ->logOnly($this->auditedAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => sprintf(
                '%s : %s',
                static::auditLabel(),
                Audit::label($event),
            ));
    }

    /**
     * Attributs suivis. Par defaut tous, sauf ceux qu'un journal ne doit pas
     * conserver — les modeles concernes surchargent cette liste.
     *
     * @return array<int, string>
     */
    protected function auditedAttributes(): array
    {
        return ['*'];
    }

    /**
     * Nom lisible du modele dans le journal.
     */
    public static function auditLabel(): string
    {
        return class_basename(static::class);
    }
}
