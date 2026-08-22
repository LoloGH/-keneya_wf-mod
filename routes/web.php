<?php

use App\Http\Controllers\Admin\AttachmentDownloadController as AdminAttachmentDownloadController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\Service\AttachmentDownloadController as ServiceAttachmentDownloadController;
use App\Http\Controllers\Service\PrescriptionPdfController;
use App\Http\Controllers\ServiceController;
use App\Support\Roles;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de KEneYa WorkFlow
|--------------------------------------------------------------------------
|
| Un role = une seule interface. Il n'existe volontairement aucune route de
| tableau de bord generique : apres authentification, HomeController renvoie
| l'utilisateur vers /admin, /reception ou /service selon son role, et le
| middleware role.scope interdit tout acces croise.
|
| /board fait exception : c'est l'affichage public de la salle d'attente,
| sans authentification et sans role.
|
*/

Route::redirect('/', '/connexion')->name('accueil');

Route::middleware('guest.only')->group(function () {
    Route::get('/connexion', [LoginController::class, 'show'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/deconnexion', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Point d'aiguillage unique vers l'interface du role connecte.
Route::get('/espace', HomeController::class)->middleware('auth')->name('home');

Route::middleware(['auth', 'role.scope:'.Roles::ADMIN])->group(function () {
    Route::get('/admin', AdminController::class)->name('admin.home');

    // Telechargement propre au role : aucune URL n'est partagee entre roles.
    Route::get('/admin/pieces-jointes/{attachment}', AdminAttachmentDownloadController::class)
        ->name('admin.attachment');
});

Route::middleware(['auth', 'role.scope:'.Roles::RECEPTIONIST])->group(function () {
    Route::get('/reception', ReceptionController::class)->name('reception.home');
});

Route::middleware(['auth', 'role.scope:'.Roles::DOCTOR])->group(function () {
    Route::get('/service', ServiceController::class)->name('service.home');

    Route::get('/service/pieces-jointes/{attachment}', ServiceAttachmentDownloadController::class)
        ->name('service.attachment');

    Route::get('/service/ordonnances/{prescription}/pdf', PrescriptionPdfController::class)
        ->name('service.prescription.pdf');
});

// Affichage public en salle d'attente (moniteur mural, sans connexion).
Route::get('/board', BoardController::class)->name('board');
