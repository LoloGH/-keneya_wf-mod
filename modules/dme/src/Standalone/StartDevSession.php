<?php

declare(strict_types=1);

namespace Keneya\Dme\Standalone;

use Keneya\Dme\Dme;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Keneya\Dme\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ouvre une session de développement (alias `dme.dev-session`).
 *
 * Le module attend normalement une session déjà authentifiée par l'hôte.
 * Tant que cet hôte n'existe pas, cet intergiciel permet de naviguer sans
 * même passer par la page de connexion locale : il ouvre la session du
 * praticien de développement décrit dans `config/dme.php`.
 *
 * Il n'est enregistré que si le mode autonome ET l'ouverture automatique
 * sont explicitement demandés, et jamais en production.
 */
class StartDevSession
{
    public function __construct(
        private readonly StandaloneMode $standalone,
        private readonly AuthFactory $auth,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->standalone->autoLogin()) {
            return $next($request);
        }

        if ($this->auth->guard()->check()) {
            return $next($request);
        }

        $user = $this->resolveDevUser();

        if ($user !== null) {
            $this->auth->guard()->login($user);
        }

        return $next($request);
    }

    /**
     * Retrouve le praticien de développement. Il doit exister : c'est le
     * seeder de développement qui le crée, pas cet intergiciel, afin
     * qu'aucun compte ne puisse apparaître au fil des requêtes.
     */
    private function resolveDevUser(): ?User
    {
        $email = $this->standalone->devUser()['email'];

        try {
            return Dme::userQuery()->where('email', $email)->first();
        } catch (\Throwable) {
            // Base absente ou migrations non jouées : on laisse la
            // requête suivre son cours sans session.
            return null;
        }
    }
}
