<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Dme;
use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Journal d'audit (§30).
 *
 * Consultation seule : aucune route d'écriture ni de suppression n'est
 * exposée, et AuditLogPolicy refuse ces actions à tous les rôles.
 */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with([
                'causer:id,name,first_name,last_name,title',
                'patient:id,patient_number,first_name,last_name',
            ])
            ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', $a))
            ->when($request->string('outcome')->toString(), fn ($q, $o) => $q->where('outcome', $o))
            ->when($request->string('user')->toString(), fn ($q, $u) => $q->where('causer_id', $u))
            ->when($request->string('from')->toString(), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->string('to')->toString(), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('dme::audit.index', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'outcome', 'user', 'from', 'to']),
            'users' => Dme::userQuery()->orderBy('last_name')->get(['id', 'name', 'first_name', 'last_name', 'title']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')->filter()->values(),
        ]);
    }
}
