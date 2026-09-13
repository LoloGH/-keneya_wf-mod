<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Centre de notifications (§33).
 *
 * Les notifications sont strictement personnelles : toutes les requêtes
 * sont filtrées sur l'utilisateur connecté, y compris le marquage comme
 * lue, un identifiant deviné ne donne accès à rien (§57, IDOR).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = DB::table('dme_notifications')
            ->where('notifiable_type', $request->user()->getMorphClass())
            ->where('notifiable_id', $request->user()->getKey())
            ->when($request->string('category')->toString(), fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('dme::notifications.index', [
            'notifications' => $notifications,
            'filters' => $request->only(['category', 'unread']),
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        DB::table('dme_notifications')
            ->where('id', $notification)
            ->where('notifiable_type', $request->user()->getMorphClass())
            ->where('notifiable_id', $request->user()->getKey())
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        DB::table('dme_notifications')
            ->where('notifiable_type', $request->user()->getMorphClass())
            ->where('notifiable_id', $request->user()->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
