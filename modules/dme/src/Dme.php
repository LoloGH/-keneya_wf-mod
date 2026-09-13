<?php

declare(strict_types=1);

namespace Keneya\Dme;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Keneya\Dme\Models\User;
use Keneya\Dme\Support\Rbac;

/**
 * Point d'entrée du module pour l'application hôte.
 *
 * Tout ce qu'un hôte (Keneya Workflow, ou une application de test) a
 * besoin de déclarer au module passe par ici. La classe ne contient
 * volontairement aucune logique métier : ce sont des points d'accroche.
 */
final class Dme
{
    /**
     * Résolveur d'autorisation d'accès fourni par l'hôte.
     *
     * @var (Closure(mixed, mixed): bool)|null
     */
    private static ?Closure $accessResolver = null;

    /**
     * Déclare comment l'hôte accorde l'accès au module.
     *
     * À appeler depuis un fournisseur de services de l'application hôte :
     *
     *     Dme::authorizeAccessUsing(
     *         fn ($user) => $user->hasPermissionTo('dossier-medical.acceder')
     *     );
     *
     * Passer `null` retire le résolveur : le module retombe alors sur la
     * capacité et l'attribut décrits dans `config/dme.php`.
     *
     * @param  (Closure(mixed, mixed): bool)|null  $callback
     */
    public static function authorizeAccessUsing(?Closure $callback): void
    {
        self::$accessResolver = $callback;
    }

    /**
     * @return (Closure(mixed, mixed): bool)|null
     */
    public static function accessResolver(): ?Closure
    {
        return self::$accessResolver;
    }

    /**
     * Remet le module dans son état initial. Réservé aux tests.
     */
    public static function flushState(): void
    {
        self::$accessResolver = null;
        self::$signatureResolver = null;
        self::$facilityResolver = null;
        self::$returnLinkResolver = null;
    }

    /**
     * Résolveur de signature et de cachets fourni par l'hôte.
     *
     * @var (Closure(mixed): array<string, ?string>)|null
     */
    private static ?Closure $signatureResolver = null;

    /**
     * Déclare où l'hôte range la signature du prescripteur et les cachets.
     *
     * Le module produit l'ordonnance, sa forme, ses lignes, son numéro, mais
     * il ne détient pas les images qui l'engagent : dans une application hôte,
     * la signature du médecin et le cachet de l'établissement appartiennent à
     * l'hôte, qui les administre et les stocke. Plutôt que d'aller les y
     * chercher, ce qui reviendrait à connaître son schéma, le module demande.
     *
     * À appeler depuis un fournisseur de services de l'hôte :
     *
     *     Dme::signaturesUsing(fn ($prescription) => [
     *         'doctorSignature' => '/chemin/absolu/signature.png',
     *         'doctorStamp'     => '/chemin/absolu/cachet.png',
     *         'facilityStamp'   => '/chemin/absolu/tampon.png',
     *     ]);
     *
     * Les chemins doivent être absolus et le fichier exister : un chemin mort
     * ferait échouer le rendu, et une ordonnance qu'on ne peut plus imprimer
     * serait pire qu'une signature absente. Chaque valeur peut être nulle.
     *
     * @param  (Closure(mixed): array<string, ?string>)|null  $callback
     */
    public static function signaturesUsing(?Closure $callback): void
    {
        self::$signatureResolver = $callback;
    }

    /**
     * Signature et cachets applicables à cet enregistrement.
     *
     * Sans hôte pour les fournir, le module tournant seul, les trois valeurs
     * sont nulles et le gabarit se rabat sur sa ligne de signature manuscrite.
     *
     * @return array{doctorSignature: ?string, doctorStamp: ?string, facilityStamp: ?string}
     */
    public static function signaturesFor(mixed $subject): array
    {
        $defaut = ['doctorSignature' => null, 'doctorStamp' => null, 'facilityStamp' => null];

        if (self::$signatureResolver === null) {
            return $defaut;
        }

        return array_merge($defaut, array_filter(
            (array) (self::$signatureResolver)($subject),
            static fn ($chemin) => is_string($chemin) && $chemin !== '' && is_file($chemin),
        ));
    }

    /**
     * Résolveur des coordonnées de l'établissement fourni par l'hôte.
     *
     * @var (Closure(): array<string, ?string>)|null
     */
    private static ?Closure $facilityResolver = null;

    /**
     * Déclare où l'hôte tient les coordonnées de l'établissement.
     *
     * Elles figurent en tête de chaque document imprimé. Une application hôte
     * les administre en général depuis son interface, et non dans un fichier
     * de configuration : sans ce point d'accroche, un changement d'adresse
     * saisi par l'administrateur ne se verrait nulle part sur les documents.
     *
     * Le résolveur est appelé au moment du rendu, jamais à l'amorçage : il
     * peut donc lire la base sans peser sur chaque requête.
     *
     * Les clés attendues sont celles de `config('dme.facility')` : `name`,
     * `address`, `phone`, `email`. Celles qui manquent gardent leur valeur de
     * configuration : un établissement sans adresse renseignée ne doit pas
     * faire disparaître son nom.
     *
     * @param  (Closure(): array<string, ?string>)|null  $callback
     */
    public static function facilityUsing(?Closure $callback): void
    {
        self::$facilityResolver = $callback;
    }

    /**
     * L'hôte tient-il lui-même les coordonnées de l'établissement ?
     *
     * Ce qui change pour l'interface : le formulaire du module devient
     * inopérant, puisque la valeur de l'hôte l'emporte au rendu. Mieux vaut ne
     * pas le proposer que laisser saisir sans effet.
     */
    public static function facilityIsProvidedByHost(): bool
    {
        return self::$facilityResolver !== null;
    }

    /**
     * Coordonnées de l'établissement, telles qu'elles doivent s'imprimer.
     *
     * @return array<string, ?string>
     */
    public static function facility(): array
    {
        $configuree = (array) config('dme.facility', []);

        if (self::$facilityResolver === null) {
            return $configuree;
        }

        return array_merge($configuree, array_filter(
            (array) (self::$facilityResolver)(),
            static fn ($valeur) => is_string($valeur) && trim($valeur) !== '',
        ));
    }

    /**
     * Lien de retour vers l'application hôte, fourni par elle.
     *
     * @var (Closure(mixed): ?array{label: string, url: string})|null
     */
    private static ?Closure $returnLinkResolver = null;

    /**
     * Déclare par où l'on retourne à l'application hôte.
     *
     * Le module est monté à l'intérieur d'une autre application : le praticien
     * y entre depuis un écran de l'hôte, et doit pouvoir en ressortir. Sans ce
     * lien, la seule issue est le bouton « précédent » du navigateur, ou la
     * déconnexion, ce qui est pire.
     *
     * Le module ne peut pas deviner cette adresse : elle dépend du rôle de la
     * personne connectée, que l'hôte seul connaît. Il demande donc.
     *
     *     Dme::returnLinkUsing(fn ($user) => [
     *         'label' => 'Retour à KEneYa WorkFlow',
     *         'url'   => $user->homeUrl(),
     *     ]);
     *
     * Rendre `null` retire le lien : le module tournant seul n'a nulle part où
     * retourner, et n'affiche alors rien.
     *
     * @param  (Closure(mixed): ?array{label: string, url: string})|null  $callback
     */
    public static function returnLinkUsing(?Closure $callback): void
    {
        self::$returnLinkResolver = $callback;
    }

    /**
     * Le lien de retour pour cette personne, ou nul.
     *
     * Une adresse vide vaut pas de lien : mieux vaut aucune porte qu'une porte
     * qui ne mène nulle part.
     *
     * @return array{label: string, url: string}|null
     */
    public static function returnLinkFor(mixed $user): ?array
    {
        if (self::$returnLinkResolver === null || $user === null) {
            return null;
        }

        $lien = (self::$returnLinkResolver)($user);

        if (! is_array($lien) || blank($lien['url'] ?? null)) {
            return null;
        }

        return [
            'label' => (string) ($lien['label'] ?? 'Retour'),
            'url' => (string) $lien['url'],
        ];
    }

    /**
     * Classe du modèle utilisateur en vigueur.
     *
     * Le module partage la table `users` avec son application hôte : c'est
     * donc le modèle de l'hôte, celui que `Auth::user()` renvoie, qui
     * doit porter les relations du dossier médical. Sans cela, le module
     * manipulerait des objets d'une autre classe que ceux de la session en
     * cours : deux instances pour la même ligne, des comparaisons
     * d'identité fausses et un `causer_type` d'audit divergent.
     *
     * L'hôte le déclare dans `config/dme.php` :
     *
     *     'models' => ['user' => \App\Models\User::class],
     *
     * À défaut, le module retombe sur son propre modèle, qui suffit quand
     * il tourne seul.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public static function userModel(): string
    {
        $model = config('dme.models.user');

        return is_string($model) && $model !== '' ? $model : User::class;
    }

    /**
     * Requête neuve sur les utilisateurs, quel que soit le modèle retenu.
     */
    public static function userQuery(): Builder
    {
        $model = self::userModel();

        return (new $model)->newQuery();
    }

    /**
     * Requête sur les comptes qui portent ce rôle du DME, traduit dans le
     * vocabulaire de l'application hôte ({@see Rbac::hostRoles()}).
     *
     * Aucun rôle correspondant en base ne ramène personne, mais ne lève
     * jamais : une liste de praticiens est un élément d'écran, elle ne doit
     * pas décider si la page s'affiche.
     */
    public static function usersWithRole(string $role): Builder
    {
        $roles = Rbac::hostRoles($role);

        return $roles === []
            ? self::userQuery()->whereRaw('1 = 0')
            : self::userQuery()->role($roles);
    }

    /**
     * Version fonctionnelle du module.
     */
    public static function version(): string
    {
        return (string) config('dme.version', '0.0.0');
    }

    /**
     * URL d'une ressource statique du module.
     *
     * Les fichiers du module sont copiés dans le répertoire public de
     * l'hôte par `php artisan vendor:publish --tag=dme-assets`. La version
     * du module est ajoutée en paramètre pour qu'une mise à jour ne serve
     * jamais un fichier mis en cache par le navigateur.
     */
    public static function asset(string $path): string
    {
        $base = trim((string) config('dme.assets.path', 'vendor/dme'), '/');

        return asset($base.'/'.ltrim($path, '/')).'?v='.self::version();
    }
}
