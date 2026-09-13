<?php

declare(strict_types=1);

namespace Keneya\Dme\Access;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Keneya\Dme\Dme;
use Keneya\Dme\Standalone\StandaloneMode;

/**
 * Autorisation d'accès de haut niveau au module.
 *
 * Le module ne décide pas qui a le droit d'ouvrir un dossier médical :
 * cette décision appartient à l'application hôte. Cette classe se borne à
 * vérifier que l'hôte l'a bien accordée, sous l'une des trois formes
 * prévues, examinées dans cet ordre :
 *
 *   1. un résolveur enregistré par l'hôte, via Dme::authorizeAccessUsing() ;
 *   2. une capacité portée par l'utilisateur (Gate ou permission),
 *      nommée par `dme.access.ability` ;
 *   3. un attribut booléen porté par l'utilisateur authentifié,
 *      nommé par `dme.access.attribute`.
 *
 * En l'absence de toute forme d'accord, l'accès est refusé. C'est
 * délibéré : un module monté sans décision explicite de l'hôte doit
 * rester fermé, plutôt que de s'ouvrir par défaut.
 *
 * Les permissions internes au DME (qui peut créer une ordonnance, voir le
 * laboratoire...) ne sont pas concernées : elles restent portées par
 * {@see \Keneya\Dme\Support\Rbac} et par les policies.
 */
final class HostAccessGate
{
    public function __construct(
        private readonly Config $config,
        private readonly Gate $gate,
        private readonly StandaloneMode $standalone,
    ) {
    }

    /**
     * L'hôte a-t-il accordé l'accès au module à cet utilisateur ?
     */
    public function allows(?Authenticatable $user, ?Request $request = null): bool
    {
        // En développement autonome, aucun hôte n'existe encore pour
        // accorder quoi que ce soit : l'autorisation est simulée.
        if ($this->standalone->enabled()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        $resolver = Dme::accessResolver();

        if ($resolver !== null) {
            return (bool) $resolver($user, $request);
        }

        $ability = $this->config->get('dme.access.ability');

        if (is_string($ability) && $ability !== '' && $this->gate->forUser($user)->allows($ability)) {
            return true;
        }

        return $this->hasAccessAttribute($user);
    }

    /**
     * Attribut booléen éventuellement posé par l'hôte sur l'utilisateur
     * (colonne, accesseur ou méthode), par exemple `can_access_dme`.
     */
    private function hasAccessAttribute(Authenticatable $user): bool
    {
        $attribute = $this->config->get('dme.access.attribute');

        if (! is_string($attribute) || $attribute === '') {
            return false;
        }

        if (method_exists($user, $attribute)) {
            return (bool) $user->{$attribute}();
        }

        return (bool) ($user->{$attribute} ?? false);
    }

    /**
     * Raison lisible d'un refus, pour le journal d'audit et la page 403.
     */
    public function denialReason(?Authenticatable $user): string
    {
        if ($user === null) {
            return (string) trans('dme::messages.access.no_session');
        }

        return (string) trans('dme::messages.access.denied');
    }
}
