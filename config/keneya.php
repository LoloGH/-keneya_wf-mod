<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identite du produit et de l'etablissement
    |--------------------------------------------------------------------------
    |
    | Le nom affiche dans l'interface et dans les SMS. C'est le seul endroit du
    | projet, avec .env.example, ou figure le caractere « E » ouvert du nom du
    | produit : il s'agit d'une chaine d'affichage, pas d'un identifiant.
    | Tous les identifiants techniques — paquet Composer, base de donnees,
    | namespaces, images et volumes Docker — s'ecrivent « keneya-workflow » en
    | ASCII pur.
    |
    | Le prefixe de code sert a construire les identifiants de dossier
    | (ex. HFD-00001) et doit etre change lors du deploiement dans un autre
    | etablissement.
    |
    */

    'name' => env('KENEYA_NAME', 'KƐnƐya WorkFlow'),

    'hospital' => env('KENEYA_HOSPITAL', 'Hopital Fousseyni Daou de Kayes'),

    'code_prefix' => env('KENEYA_CODE_PREFIX', 'HFD'),

    /*
    |--------------------------------------------------------------------------
    | Rafraichissement des ecrans
    |--------------------------------------------------------------------------
    |
    | Intervalle de polling Livewire (wire:poll). Pas de WebSocket dans cette
    | phase : un rafraichissement toutes les quelques secondes suffit et reste
    | robuste sur une connexion limitee.
    |
    */

    'poll_interval' => env('KENEYA_POLL_INTERVAL', '10s'),

    'board_poll_interval' => env('KENEYA_BOARD_POLL_INTERVAL', '5s'),

    /*
    |--------------------------------------------------------------------------
    | Mot de passe des comptes de demonstration
    |--------------------------------------------------------------------------
    |
    | Utilise par DemoStaffSeeder. Il passe par la configuration (et non par
    | env() dans le seeder) afin de rester lisible meme lorsque la
    | configuration est mise en cache par `php artisan config:cache`.
    |
    | A changer avant toute mise en service reelle.
    |
    */

    'seed_password' => env('SEED_DEFAULT_PASSWORD', 'motdepasse'),

];
