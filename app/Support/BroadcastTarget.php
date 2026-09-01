<?php

namespace App\Support;

use App\Models\BroadcastMessage;

/**
 * La cible d'une diffusion, telle que l'administrateur l'a choisie
 * (v3.2.9, point 1).
 *
 * Un petit objet plutot qu'un tableau de parametres qui circulerait de methode
 * en methode : c'est lui qui garantit que l'apercu du nombre de destinataires
 * et l'envoi reel parlent exactement de la meme population.
 */
final class BroadcastTarget
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $staffUserId = null,
        public readonly ?int $patientId = null,
        public readonly ?int $serviceId = null,
        public readonly ?int $pathologyId = null,
    ) {}

    /**
     * Les filtres retenus, pour `broadcast_messages.target_filters`. Seuls les
     * criteres reellement appliques y figurent : un filtre nul n'a pas a
     * encombrer la trace.
     *
     * @return array<string, int>
     */
    public function filters(): array
    {
        return array_filter([
            'staff_user_id' => $this->staffUserId,
            'patient_id' => $this->patientId,
            'service_id' => $this->serviceId,
            'pathology_id' => $this->pathologyId,
        ], fn (?int $valeur) => $valeur !== null);
    }

    public function isGroup(): bool
    {
        return in_array($this->type, [
            BroadcastMessage::TARGET_PATIENT_GROUP,
            BroadcastMessage::TARGET_ALL_PATIENTS,
        ], true);
    }
}
