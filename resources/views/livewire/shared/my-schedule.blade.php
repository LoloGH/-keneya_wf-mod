<x-card title="Mon planning" icon="planning">
    <p class="hint">Vos creneaux des {{ $days }} prochains jours. Seul l'administrateur peut les modifier.</p>

    @if ($schedules->isEmpty())
        <p class="empty">Aucun creneau enregistre sur cette periode.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Horaire</th><th>Service</th></tr>
                </thead>
                <tbody>
                    @foreach ($schedules as $schedule)
                        <tr @class(['schedule--today' => $schedule->date->isToday()])>
                            <td>{{ $schedule->date->translatedFormat('D d/m/Y') }}</td>
                            <td class="mono">{{ $schedule->range() }}</td>
                            <td>{{ $schedule->service?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
