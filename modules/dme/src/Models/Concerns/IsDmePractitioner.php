<?php

declare(strict_types=1);

namespace Keneya\Dme\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Keneya\Dme\Models\Appointment;
use Keneya\Dme\Models\CareOrder;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\Prescription;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Models\UserDutyPeriod;
use Keneya\Dme\Models\UserWeeklySchedule;

/**
 * Ce qu'un compte doit savoir faire pour être un praticien du DME.
 *
 * Le module partage la table `users` avec son application hôte : c'est le
 * même compte, la même session, la même ligne en base. Il ne peut donc pas
 * imposer son propre modèle utilisateur, mais il a besoin, de ce modèle
 * quel qu'il soit, d'un petit nombre de relations et de réponses : à quel
 * service la personne est rattachée, son horaire, ses gardes, son nom
 * d'affichage, et si son compte est encore actif.
 *
 * Ce trait porte exactement cela, et rien d'autre. {@see \Keneya\Dme\Models\User}
 * l'utilise quand le module tourne seul ; l'application hôte l'ajoute à son
 * propre modèle `User` quand elle le monte. Les deux chemins donnent alors
 * le même comportement, sans duplication.
 *
 * Les colonnes correspondantes (`service_id`, `is_active`, `is_on_duty`,
 * `first_name`, `title`...) sont ajoutées à la table `users` par les
 * migrations du module, qu'elle appartienne au module ou à l'hôte.
 */
trait IsDmePractitioner
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function weeklySchedules(): HasMany
    {
        return $this->hasMany(UserWeeklySchedule::class, 'user_id');
    }

    public function dutyPeriods(): HasMany
    {
        return $this->hasMany(UserDutyPeriod::class, 'user_id');
    }

    /**
     * Le compte est-il, d'après son horaire hebdomadaire type, censé être
     * en poste à l'instant présent ? Purement indicatif : contrairement à
     * is_on_duty, cet horaire ne conditionne aucun accès ni aucune
     * visibilité, il aide seulement à repérer qui devrait être présent.
     */
    public function isScheduledNow(): bool
    {
        $today = $this->weeklySchedules->firstWhere('weekday', now()->dayOfWeekIso);

        if (! $today || $today->isRestDay()) {
            return false;
        }

        $now = now()->format('H:i:s');

        return $now >= $today->starts_at && $now < $today->ends_at;
    }

    /**
     * De garde : un compte désactivé ne l'est jamais, quel que soit le
     * drapeau, il n'a plus accès à l'application.
     */
    public function isOnDuty(): bool
    {
        return $this->isActive() && (bool) $this->is_on_duty;
    }

    public function prescribedCareOrders(): HasMany
    {
        return $this->hasMany(CareOrder::class, 'prescriber_id');
    }

    public function assignedCareOrders(): HasMany
    {
        return $this->hasMany(CareOrder::class, 'assigned_nurse_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'doctor_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'doctor_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'attending_doctor_id');
    }

    /**
     * Nom d'affichage complet, titre professionnel inclus.
     */
    public function displayName(): string
    {
        $parts = array_filter([
            $this->title,
            $this->first_name ?: null,
            $this->last_name ?: null,
        ]);

        return $parts === [] ? (string) $this->name : implode(' ', $parts);
    }

    public function initials(): string
    {
        $source = $this->first_name && $this->last_name
            ? $this->first_name.' '.$this->last_name
            : (string) $this->name;

        $initials = collect(preg_split('/\s+/', trim($source)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Un compte désactivé conserve son historique mais ne peut plus agir.
     *
     * La colonne `is_active` est posée par les migrations du module. Une
     * base où elle manque encore, module installé, migrations pas encore
     * jouées, ne doit pas verrouiller tout le monde : l'absence de valeur
     * vaut compte actif.
     */
    public function isActive(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function auditLabel(): string
    {
        return $this->displayName();
    }
}
