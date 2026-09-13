<?php

declare(strict_types=1);

namespace Keneya\Dme\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rules\Password;

/**
 * Politique de mot de passe du module (§41).
 *
 * Le module définit sa propre règle plutôt que d'imposer un
 * `Password::defaults()` global : cette valeur par défaut appartient à
 * l'application hôte, qui a ses propres écrans de compte. Le DME
 * n'applique donc sa politique qu'aux mots de passe qu'il gère lui-même.
 */
final class PasswordPolicy
{
    /**
     * Longueur minimale, complexité, et refus des mots de passe présents
     * dans les fuites connues en production uniquement : l'appel réseau
     * associé n'a pas sa place en développement ni dans les tests.
     */
    public static function rule(): Password
    {
        $rule = Password::min(12)->letters()->mixedCase()->numbers();

        return App::isProduction()
            ? $rule->symbols()->uncompromised()
            : $rule;
    }
}
