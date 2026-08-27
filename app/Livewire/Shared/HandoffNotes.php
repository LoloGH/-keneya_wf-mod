<?php

namespace App\Livewire\Shared;

use App\Actions\AddHandoffNote;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\HandoffNote;
use App\Models\Hospitalization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Notes de releve d'un sejour, partagees par /service et /staff/{slug}
 * (v3.2.3, point 4).
 *
 * Un seul composant pour les deux interfaces : ce que l'equipe de nuit ecrit et
 * ce que l'equipe de jour lit sont la meme chose, et deux composants auraient
 * fini par diverger.
 *
 * La cle de message est fournie par la page qui monte le composant : /service
 * et /staff n'utilisent pas les memes.
 */
class HandoffNotes extends Component
{
    use NotifiesUser;

    public int $hospitalizationId;

    public string $content = '';

    /** Prefixe des cles de session, selon l'interface hote. */
    public string $flashKey = 'service';

    public function mount(int $hospitalizationId, string $flashKey = 'service'): void
    {
        $this->hospitalizationId = $hospitalizationId;
        $this->flashKey = $flashKey;
    }

    public function save(AddHandoffNote $action): void
    {
        $this->validate(
            ['content' => ['required', 'string', 'max:2000']],
            attributes: ['content' => 'note'],
        );

        try {
            $action->execute(
                Hospitalization::findOrFail($this->hospitalizationId),
                Auth::user(),
                $this->content,
            );
        } catch (InvalidArgumentException $e) {
            $this->notifyError($e->getMessage(), $this->flashKey.'.error');

            return;
        }

        $this->reset('content');
        $this->resetValidation();

        $this->notifySuccess('Note de releve enregistree.', $this->flashKey.'.status');
    }

    /**
     * @return Collection<int, HandoffNote>
     */
    private function notes(): Collection
    {
        return HandoffNote::with('writtenBy')
            ->where('hospitalization_id', $this->hospitalizationId)
            ->latest('id')
            ->limit(20)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.shared.handoff-notes', [
            'notes' => $this->notes(),
            // De garde ou non : hors garde, la note se lit mais ne s'ecrit pas,
            // exactement comme les soins ne se marquent pas.
            'onDuty' => Auth::user()?->isOnDutyFor(
                Hospitalization::whereKey($this->hospitalizationId)->value('service_id') ?? 0
            ) ?? false,
        ]);
    }
}
