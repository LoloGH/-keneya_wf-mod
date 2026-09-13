<?php

declare(strict_types=1);

namespace Keneya\Dme\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle de message SMS (§35).
 *
 * Appartient au service SMS transversal : ne référence aucun modèle du
 * domaine médical, afin de rester extractible en phase 2.
 */
class SmsTemplate extends Model
{
    protected $table = 'dme_sms_templates';

    use HasFactory;

    protected $fillable = ['key', 'name', 'body', 'variables', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    /**
     * Remplace les variables {{ nom }} par les valeurs fournies.
     *
     * @param  array<string, string|int|null>  $variables
     */
    public function render(array $variables = []): string
    {
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $body = str_replace(
                ['{{ '.$key.' }}', '{{'.$key.'}}'],
                (string) $value,
                $body
            );
        }

        return $body;
    }
}
