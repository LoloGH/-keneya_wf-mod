<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de l'application hôte de test
|--------------------------------------------------------------------------
|
| Une seule page : le point d'entrée depuis lequel l'hôte ouvre le module,
| comme le fera « Mes patients » dans Keneya Workflow. Tout le reste est
| servi par le module, sous son propre préfixe.
|
*/

Route::get('/', fn () => view('workbench::accueil'))->name('accueil');
