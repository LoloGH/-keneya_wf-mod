<?php

declare(strict_types=1);

namespace Keneya\Dme\Standalone;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Mode autonome de développement.
 *
 * Le module est conçu pour fonctionner sous une session authentifiée
 * fournie par l'application hôte. Tant que cet hôte n'existe pas, ce mode
 * permet de continuer à naviguer dans le DME et à le vérifier
 * visuellement : il ouvre une page de connexion locale, peut fabriquer
 * une session de développement et considère l'autorisation d'accès de
 * haut niveau comme accordée.
 *
 * Deux garde-fous, volontairement redondants :
 *
 *   1. il faut une variable d'environnement explicite (DME_STANDALONE_DEV) ;
 *   2. il ne s'active jamais quand l'application tourne en production,
 *      quelle que soit la valeur de cette variable.
 */
final class StandaloneMode
{
    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
    ) {
    }

    /**
     * Le mode autonome est-il réellement actif ?
     */
    public function enabled(): bool
    {
        if (! (bool) $this->config->get('dme.standalone.enabled', false)) {
            return false;
        }

        // Un environnement de production ne doit jamais s'authentifier
        // lui-même : la demande est ignorée plutôt que suivie.
        return ! $this->app->isProduction();
    }

    /**
     * Le mode autonome doit-il ouvrir une session tout seul ?
     */
    public function autoLogin(): bool
    {
        return $this->enabled() && (bool) $this->config->get('dme.standalone.auto_login', false);
    }

    /**
     * A-t-on demandé le mode autonome alors qu'il est refusé ?
     * Sert à le signaler dans les journaux au démarrage.
     */
    public function refusedInProduction(): bool
    {
        return (bool) $this->config->get('dme.standalone.enabled', false)
            && $this->app->isProduction();
    }

    /**
     * Description de l'utilisateur de développement.
     *
     * @return array{email: string, name: string, role: string}
     */
    public function devUser(): array
    {
        return [
            'email' => (string) $this->config->get('dme.standalone.user.email', 'dev@keneya.test'),
            'name' => (string) $this->config->get('dme.standalone.user.name', 'Praticien de développement'),
            'role' => (string) $this->config->get('dme.standalone.user.role', 'administrateur'),
        ];
    }
}
