<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Taches planifiees
|--------------------------------------------------------------------------
|
| Premiere et seule tache periodique de l'application (v3.2.3, point 2).
| Elle n'a rien a executer si aucun rendez-vous n'approche : la faire tourner
| souvent ne coute qu'une requete indexee.
|
| Elle ne s'execute que si un scheduler tourne reellement — le service
| `scheduler` du docker-compose. Sans lui, les rappels ne partiront jamais et
| rien ne le signalera.
*/
Schedule::command('keneya:rappels-rendez-vous')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
| Invitations des visiteurs a donner leur avis (v3.2.8, point 4).
|
| Le delai lui-meme se regle dans les parametres ; cette commande se contente
| de repasser regulierement pour voir qui l'a depasse. Toutes les quinze
| minutes suffisent : un quart d'heure de decalage sur un delai de trois heures
| ne se remarque pas, et la requete est indexee.
*/
Schedule::command('keneya:liens-avis-visiteurs')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
