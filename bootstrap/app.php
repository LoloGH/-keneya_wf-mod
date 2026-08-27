<?php

use App\Http\Middleware\EnsureRoleScope;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role.scope' => EnsureRoleScope::class,
            'guest.only' => RedirectIfAuthenticated::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Sans ce middleware, `Auth::logoutOtherDevices()` ne ferme rien : il
        // se contente de reecrire le hash dans la session courante, et les
        // sessions ouvertes ailleurs continuent de fonctionner. C'est lui qui
        // les compare a chaque requete et les invalide (v3.2.3, point 3).
        //
        // Les sessions deja ouvertes au moment du deploiement ne sont pas
        // coupees : sans marqueur en session, il le pose et laisse passer.
        $middleware->web(append: [AuthenticateSession::class]);

        // Le cloisonnement doit etre tranche AVANT la resolution des modeles de
        // route : sinon un utilisateur du mauvais role recoit un 404 quand
        // l'identifiant n'existe pas, et une redirection quand il existe — ce
        // qui lui permettrait de deviner les identifiants d'un autre service.
        $middleware->priority([
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            EnsureRoleScope::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
