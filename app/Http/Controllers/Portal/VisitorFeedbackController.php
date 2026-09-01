<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Contracts\View\View;

/**
 * Page publique de retour d'un visiteur (v3.2.8, point 4).
 *
 * Le jeton est verifie ici pour qu'une adresse inventee rende un 404 sans
 * jamais atteindre le composant.
 */
class VisitorFeedbackController extends Controller
{
    public function __invoke(string $token): View
    {
        Visitor::where('feedback_token', $token)->firstOrFail();

        return view('pages.visitor-feedback', ['token' => $token]);
    }
}
