<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web\Auth;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Authentification par session (§41).
 *
 * La limitation de débit est appliquée sur la route (throttle:login) ;
 * les connexions réussies comme les échecs sont journalisés dans le
 * registre d'audit. La session est régénérée à la connexion et invalidée
 * à la déconnexion pour écarter la fixation de session.
 */
class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dme.dashboard');
        }

        return view('dme::auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::record(
                action: 'login_failed',
                outcome: 'denied',
                description: 'Échec de connexion pour '.$credentials['email'],
            );

            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte actif.',
            ]);
        }

        $user = Auth::user();

        // Un compte désactivé conserve son historique mais ne peut plus se connecter.
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();

            AuditLog::record(
                action: 'login_failed',
                outcome: 'denied',
                description: 'Connexion refusée, compte désactivé : '.$credentials['email'],
            );

            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez un administrateur.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        AuditLog::record(action: 'login', subject: $user, description: 'S\'est connecté');

        return redirect()->intended(route('dme.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record(action: 'logout', subject: $request->user(), description: 'S\'est déconnecté');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dme.login')->with('status', 'Vous êtes déconnecté.');
    }
}
