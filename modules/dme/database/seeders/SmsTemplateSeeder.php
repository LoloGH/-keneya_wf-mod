<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Seeders;

use Keneya\Dme\Models\SmsTemplate;
use Illuminate\Database\Seeder;

/**
 * Modèles de SMS (§36).
 *
 * Les textes reprennent les exemples de la spécification. Ils restent
 * volontairement courts et sans donnée clinique : un SMS transite par un
 * réseau non maîtrisé, il ne doit jamais contenir de résultat médical.
 */
class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'appointment_scheduled',
                'name' => 'Rendez-vous programmé',
                'body' => 'Keneya : Bonjour {{ patient_name }}, votre rendez-vous médical est prévu le {{ date }} à {{ time }}.',
                'variables' => ['patient_name', 'date', 'time', 'doctor'],
                'description' => 'Confirmation envoyée à la création d\'un rendez-vous.',
            ],
            [
                'key' => 'appointment_reminder',
                'name' => 'Rappel de rendez-vous',
                'body' => 'Keneya : Rappel : votre rendez-vous est prévu demain à {{ time }}.',
                'variables' => ['patient_name', 'time'],
                'description' => 'Rappel programmé la veille du rendez-vous.',
            ],
            [
                'key' => 'lab_result_available',
                'name' => 'Résultat disponible',
                'body' => 'Keneya : Votre résultat d\'analyse est disponible. Présentez-vous avec votre pièce d\'identité.',
                'variables' => ['patient_name', 'reference'],
                'description' => 'Aucun résultat clinique n\'est transmis par SMS.',
            ],
            [
                'key' => 'prescription_ready',
                'name' => 'Ordonnance enregistrée',
                'body' => 'Keneya : Votre ordonnance a été enregistrée. Référence : {{ reference }}.',
                'variables' => ['patient_name', 'reference'],
                'description' => 'Envoyé à la validation d\'une ordonnance.',
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template + ['is_active' => true],
            );
        }
    }
}
