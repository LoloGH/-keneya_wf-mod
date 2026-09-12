<x-layouts.portal
    :title="($result->valid ? 'Document verifie' : 'Document non verifie').' - '.config('keneya.name')"
    :subtitle="'Verification de document'"
>
    <section class="card verification verification--{{ $result->valid ? 'valide' : 'invalide' }}">
        <div class="verification__etat">
            <span class="verification__icone" aria-hidden="true">
                <x-icon :name="$result->valid ? 'valide' : 'fermer'" size="28" />
            </span>
            <div>
                <h1 class="verification__titre">
                    {{ $result->valid ? 'Document verifie' : 'Document non verifie' }}
                </h1>
                <p class="verification__soustitre">
                    @if ($result->valid)
                        Cette reference existe dans le dossier medical de l'etablissement.
                    @else
                        Cette reference est introuvable ou invalide.
                    @endif
                </p>
            </div>
        </div>

        @if ($result->valid)
            <dl class="verification__details">
                <div>
                    <dt>Type</dt>
                    <dd>{{ $result->type }}</dd>
                </div>
                <div>
                    <dt>Reference</dt>
                    <dd class="mono">{{ $result->reference }}</dd>
                </div>
                @if ($result->facility)
                    <div>
                        <dt>Etablissement</dt>
                        <dd>{{ $result->facility }}</dd>
                    </div>
                @endif
                @if ($result->date)
                    <div>
                        <dt>Date</dt>
                        <dd>{{ $result->date->format('d/m/Y') }}</dd>
                    </div>
                @endif
                @if ($result->status)
                    <div>
                        <dt>Statut</dt>
                        <dd>{{ $result->status }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </section>
</x-layouts.portal>
