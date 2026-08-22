<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloisonnement strict : un role, une seule interface.
 *
 * Si un utilisateur atteint une interface qui n'est pas la sienne (typiquement
 * en tapant l'URL a la main), il est renvoye vers la sienne avec un message
 * lisible plutot que sur une page d'erreur 403 : le personnel hospitalier ne
 * doit jamais rester bloque devant une erreur technique.
 */
class EnsureRoleScope
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasRole($role)) {
            return $next($request);
        }

        $home = $user->homeRoute();

        if (! $home) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'error',
                "Aucun role n'est associe a votre compte. Contactez l'administrateur."
            );
        }

        return redirect()->route($home)->with('error', sprintf(
            'Cette page est reservee au role « %s ». Vous avez ete redirige vers votre espace.',
            Roles::label($role),
        ));
    }
}
