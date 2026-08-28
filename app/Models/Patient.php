<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * L'identite permanente d'un patient.
 *
 * Depuis l'addendum v3, cette table ne porte plus ni service, ni ticket, ni
 * statut : tout cela appartient a un passage (App\Models\Visit). Le
 * `patient_code` est attribue une seule fois, a la toute premiere venue, et
 * ne change jamais.
 */
class Patient extends Model
{
    use HasFactory, RecordsActivity;

    protected $fillable = [
        'patient_code',
        'name',
        'age',
        'gender',
        'profession',
        'mobile',
        'crno',
        'note',
        'access_code',
        'portal_token',
    ];

    /**
     * Le code d'acces ne doit pas partir dans une serialisation par megarde.
     *
     * @var array<int, string>
     */
    protected $hidden = ['access_code'];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * Le passage le plus recent, quel que soit son statut.
     */
    public function latestVisit(): HasOne
    {
        return $this->hasOne(Visit::class)->latestOfMany('opened_at');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    public function companions(): HasMany
    {
        return $this->hasMany(Companion::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function portalAccessAttempt(): HasOne
    {
        return $this->hasOne(PortalAccessAttempt::class);
    }

    /**
     * Identifiant du patient tel qu'on le prononce a l'accueil.
     */
    public function label(): string
    {
        return sprintf('%s (%s)', $this->name, $this->patient_code);
    }

    /**
     * Le code d'acces et le jeton du portail n'ont rien a faire dans un
     * journal consultable par l'administration.
     *
     * @return array<int, string>
     */
    protected function auditedAttributes(): array
    {
        return ['patient_code', 'name', 'age', 'gender', 'mobile', 'crno'];
    }

    public static function auditLabel(): string
    {
        return 'Patient';
    }
}
