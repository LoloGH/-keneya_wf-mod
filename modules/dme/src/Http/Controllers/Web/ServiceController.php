<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Services / unités fonctionnelles de l'établissement, gérés depuis
 * l'écran Paramètres. Un service n'est jamais supprimé une fois créé :
 * il peut être rattaché à des utilisateurs, consultations et
 * hospitalisations ; le désactiver (is_active) retire son usage sans
 * perdre l'historique qui le référence.
 */
class ServiceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_dash', 'unique:dme_services,code'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:clinical,medico_technical,administrative'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], [
            'code' => 'code',
            'name' => 'nom',
            'type' => 'type',
            'description' => 'description',
        ]);

        $data['code'] = mb_strtoupper($data['code']);

        $service = Service::create($data + ['is_active' => true]);

        AuditLog::record(
            action: 'service_created',
            subject: $service,
            description: 'A créé le service '.$service->name,
        );

        return back()->with('success', 'Service « '.$service->name.' » créé.');
    }
}
