<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Le resultat, volontairement pauvre, d'une verification publique de
 * document par QR code (dossier medical, v3.4).
 *
 * Une personne qui scanne un QR code n'a droit qu'a la preuve que le
 * document existe, pas a son contenu : ces cinq champs sont donc la totalite
 * de ce qui peut jamais transiter par cet objet. Ajouter un champ ici revient
 * a decider qu'il peut s'afficher sans authentification ni piece d'identite,
 * ce qui doit rester une decision explicite et jamais un effet de bord.
 */
final class DocumentVerificationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly ?string $type = null,
        public readonly ?string $reference = null,
        public readonly ?string $facility = null,
        public readonly ?Carbon $date = null,
        public readonly ?string $status = null,
    ) {}

    public static function valid(
        string $type,
        string $reference,
        ?string $facility,
        ?Carbon $date,
        ?string $status,
    ): self {
        return new self(
            valid: true,
            type: $type,
            reference: $reference,
            facility: $facility,
            date: $date,
            status: $status,
        );
    }

    /**
     * Reference introuvable, invalide ou de prefixe inconnu : les trois cas
     * meritent la meme reponse publique, pour ne jamais laisser deviner
     * lequel des trois s'est produit.
     */
    public static function invalid(): self
    {
        return new self(valid: false);
    }
}
