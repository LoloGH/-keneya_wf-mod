@extends('dme::layouts.app')

@section('title', 'Modifier le compte')

@section('content')
    <x-dme::page-header title="Modifier le compte" :subtitle="$user->displayName()"
                   :breadcrumbs="['Utilisateurs' => route('dme.users.index'), $user->displayName() => null]"/>

    <form action="{{ route('dme.users.update', $user) }}" method="POST" novalidate>
        @csrf
        @method('PUT')
        @include('dme::users._form')
        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="k-btn-primary">Enregistrer</button>
            <a href="{{ route('dme.users.index') }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
