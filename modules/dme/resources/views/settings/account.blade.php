@extends('dme::layouts.app')

@section('title', 'Paramètres')

@section('content')
    {{-- Écran servi aux comptes sans settings.manage.
         La configuration de l'établissement, les passerelles SMS et la
         matrice de permissions ne leur sont pas rendues : ce n'est pas un
         masquage d'affichage, le contrôleur ne les charge pas. --}}
    <x-dme::page-header title="Paramètres"
                   subtitle="Votre compte et votre mot de passe. La configuration de l'application relève de l'administration."/>

    @include('dme::settings.partials.account')

    <section class="k-card mt-4">
        <div class="k-card-header"><h2 class="k-card-title">Besoin d'autre chose ?</h2></div>
        <div class="k-card-body text-sm text-ink-600">
            <p>
                La création de comptes, les rôles et permissions, les services et la configuration des
                envois SMS sont réservés à l'administration de l'établissement.
            </p>
            <p class="mt-2">
                Pour une correction sur votre fiche, nom, service, spécialité, matricule, adressez-vous
                à un administrateur : ces informations signent vos actes dans les dossiers patients et ne
                peuvent pas être modifiées par leur titulaire.
            </p>
        </div>
    </section>
@endsection
