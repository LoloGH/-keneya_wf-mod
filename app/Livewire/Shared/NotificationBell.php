<?php

namespace App\Livewire\Shared;

use App\Models\StaffNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Cloche de notification, partagee par les cinq interfaces (v3.2.3, point 2).
 *
 * `wire:poll` a dix secondes, comme le reste de l'application : pas de
 * WebSocket dans cette version, pour la meme raison qu'ailleurs — une
 * dependance de plus a faire tourner sur le VPS pour un gain que l'usage n'a
 * pas encore reclame.
 *
 * Le composant ne joue aucun son lui-meme : il expose le nombre de non-lues,
 * et c'est le navigateur qui compare l'ancien au nouveau. Un son emis cote
 * serveur sonnerait a chaque sondage.
 */
class NotificationBell extends Component
{
    /** Panneau deplie. */
    public bool $open = false;

    /** Nombre d'entrees listees dans le panneau. */
    public const RECENTES = 12;

    /**
     * Dernier total connu de non-lues.
     *
     * C'est lui qui distingue « il y a du nouveau » de « rien n'a bouge » :
     * sans cette memoire, la cloche sonnerait a chaque sondage, donc toutes
     * les dix secondes, pour des notifications deja vues.
     */
    public int $lastCount = 0;

    public function mount(): void
    {
        // Le total du premier rendu n'est pas une nouveaute : on n'accueille
        // pas quelqu'un qui se connecte par une sonnerie.
        $this->lastCount = $this->unreadCount();
    }

    /**
     * Sondage : le son ne part que si le total a augmente.
     */
    public function refresh(): void
    {
        $count = $this->unreadCount();

        if ($count > $this->lastCount) {
            $this->dispatch('notification-nouvelle');
        }

        $this->lastCount = $count;
    }

    public function unreadCount(): int
    {
        return Auth::check()
            ? StaffNotification::where('user_id', Auth::id())->unread()->count()
            : 0;
    }

    /**
     * Ouvre le panneau et marque comme lues les notifications montrees.
     *
     * Marquer a l'ouverture plutot qu'au clic sur chaque ligne : la cloche dit
     * « il y a du nouveau », et regarder la liste suffit a repondre a cette
     * question. Exiger un clic par ligne ferait vivre un badge qui ne redescend
     * jamais.
     */
    public function toggle(): void
    {
        $this->open = ! $this->open;

        if (! $this->open || ! Auth::check()) {
            return;
        }

        StaffNotification::where('user_id', Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        // Le compteur repart de zero : la prochaine notification sonnera.
        $this->lastCount = 0;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * @return Collection<int, StaffNotification>
     */
    public function recent(): Collection
    {
        if (! Auth::check()) {
            return collect();
        }

        return StaffNotification::where('user_id', Auth::id())
            ->latest('id')
            ->limit(self::RECENTES)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.shared.notification-bell', [
            'unread' => $this->unreadCount(),
            'notifications' => $this->open ? $this->recent() : collect(),
        ]);
    }
}
