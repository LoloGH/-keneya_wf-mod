<?php

namespace App\Livewire\Reception;

use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Liste des passages du jour (patients et visiteurs) vue depuis l'accueil.
 */
class TodayVisits extends Component
{
    public string $search = '';

    #[On('patient-enregistre')]
    #[On('visiteur-enregistre')]
    public function refreshList(): void
    {
        // Le simple fait de recevoir l'evenement declenche un nouveau rendu.
    }

    public function render(): View
    {
        $search = trim($this->search);

        $visits = Visit::query()
            ->with(['patient', 'service'])
            ->whereDate('opened_at', today())
            ->when($search !== '', fn ($query) => $query->whereHas('patient', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('patient_code', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            }))
            ->orderByDesc('id')
            ->get();

        $visitors = Visitor::query()
            ->with(['service', 'patient'])
            ->whereDate('created_at', today())
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('visitor_code', 'like', "%{$search}%");
            }))
            ->orderByDesc('id')
            ->get();

        return view('livewire.reception.today-visits', [
            'visits' => $visits,
            'visitors' => $visitors,
        ]);
    }
}
