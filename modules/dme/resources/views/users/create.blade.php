@extends('dme::layouts.app')

@section('title', 'Nouveau compte')

@section('content')
    <x-dme::page-header title="Nouveau compte professionnel"
                   :breadcrumbs="['Utilisateurs' => route('dme.users.index'), 'Nouveau compte' => null]"/>

    <form action="{{ route('dme.users.store') }}" method="POST" novalidate>
        @csrf
        @include('dme::users._form', ['user' => null])
        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="k-btn-primary">Créer le compte</button>
            <a href="{{ route('dme.users.index') }}" class="k-btn-ghost">Annuler</a>
        </div>
    </form>
@endsection
