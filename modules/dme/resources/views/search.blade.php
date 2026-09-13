@extends('dme::layouts.app')

@section('title', 'Recherche')

@section('content')
    <x-dme::page-header title="Recherche globale"
                   :subtitle="$term ? 'Résultats pour « '.$term.' »' : 'Saisissez au moins deux caractères dans la barre de recherche.'"/>

    @if ($groups->isEmpty())
        <div class="k-card">
            <x-dme::empty-state icon="search"
                           :title="$term ? 'Aucun résultat' : 'Lancez une recherche'"
                           message="La recherche porte sur les patients, consultations, ordonnances, examens, documents et rendez-vous auxquels vous avez accès."/>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($groups as $category => $results)
                <section class="k-card">
                    <div class="k-card-header">
                        <h2 class="k-card-title">{{ $category }}</h2>
                        <span class="text-xs text-ink-500">{{ $results->count() }}</span>
                    </div>
                    <ul class="divide-y divide-ink-100">
                        @foreach ($results as $result)
                            <li>
                                <a href="{{ $result['url'] }}" class="block px-4 py-3 hover:bg-clinic-50/40">
                                    <p class="text-sm font-medium text-ink-900">{{ $result['title'] }}</p>
                                    <p class="text-xs text-ink-500">{{ $result['subtitle'] }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
@endsection
