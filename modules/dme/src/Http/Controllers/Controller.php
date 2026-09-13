<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Contrôleur de base.
 *
 * `AuthorizesRequests` est activé pour l'ensemble de l'application : tout
 * contrôleur peut donc appeler `$this->authorize(...)`, ce qui rend la
 * vérification d'autorisation systématique et lisible (§32).
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
