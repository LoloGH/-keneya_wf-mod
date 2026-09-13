<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Keneya\Dme\Access\HostAccessGate;
use Keneya\Dme\Models\AuditLog;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porte d'entrée du module (alias `dme.access`).
 *
 * Appliqué à toutes les routes web du module, avant toute vérification
 * de permission interne : on ne regarde pas d'abord ce que l'utilisateur
 * a le droit de faire dans le DME, mais si l'application hôte lui a
 * seulement accordé le droit d'y entrer.
 *
 * Une requête anonyme traverse cet intergiciel sans être refusée ici :
 * c'est l'intergiciel `auth`, déclaré sur les routes, qui la redirigera
 * vers l'authentification de l'hôte. Refuser ici renverrait un 403 là où
 * une redirection de connexion est attendue.
 */
class EnsureHostGrantsAccess
{
    public function __construct(private readonly HostAccessGate $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($this->access->allows($user, $request)) {
            return $next($request);
        }

        $this->recordDenial($request);

        abort(403, $this->access->denialReason($user));
    }

    /**
     * Un refus d'accès au dossier médical est un événement de sécurité :
     * il est tracé comme tel, sans interrompre la réponse si le journal
     * n'est pas disponible (installation incomplète, table absente).
     */
    private function recordDenial(Request $request): void
    {
        try {
            AuditLog::record(
                action: 'dme_access_denied',
                outcome: 'denied',
                description: "Accès au module refusé : l'hôte ne l'a pas accordé (".$request->path().').',
            );
        } catch (\Throwable) {
            // Le refus reste appliqué même si la trace échoue.
        }
    }
}
