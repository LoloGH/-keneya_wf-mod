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
