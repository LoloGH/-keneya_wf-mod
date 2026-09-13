<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Textes du module, adressables par l'application hôte
|--------------------------------------------------------------------------
|
| Les écrans du DME sont rédigés en français dans les vues : ce fichier ne
| duplique pas cette prose. Il ne porte que les messages qu'un hôte peut
| légitimement vouloir reformuler à son image : au premier rang desquels
| le refus d'accès, qui s'affiche avant même que l'utilisateur soit entré
| dans le module.
|
| Publiable : php artisan vendor:publish --tag=dme-lang
|
*/

return [

    'access' => [
        'denied' => "L'accès au dossier médical électronique ne vous a pas été accordé.",
        'no_session' => 'Aucune session authentifiée.',
    ],

];
