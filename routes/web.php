<?php

use App\Http\Controllers\Admin\AttachmentDownloadController as AdminAttachmentDownloadController;
use App\Http\Controllers\Admin\PrintableController as AdminPrintableController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\Caisse\CaisseReceiptController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\DmeRecordController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Portal\PatientPortalController;
use App\Http\Controllers\Portal\PortalDownloadController;
use App\Http\Controllers\Portal\PortalPrescriptionPdfController;
use App\Http\Controllers\Portal\VisitorFeedbackController;
use App\Http\Controllers\Reception\PrintTicketController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\Service\AttachmentDownloadController as ServiceAttachmentDownloadController;
use App\Http\Controllers\Service\PrescriptionPdfController;
use App\Http\Controllers\Service\PrintableController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\Staff\StaffPrintTicketController;
use App\Http\Controllers\StaffInterfaceController;
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

    // Rendus imprimables : le navigateur declenche l'impression, pas le serveur.
    Route::get('/admin/pieces-jointes/{attachment}/impression', [AdminPrintableController::class, 'attachment'])
        ->name('admin.attachment.print');

    Route::get('/admin/ordonnances/{prescription}/impression', [AdminPrintableController::class, 'prescription'])
        ->name('admin.prescription.print');
});

Route::middleware(['auth', 'role.scope:'.Roles::RECEPTIONIST])->group(function () {
    Route::get('/reception', ReceptionController::class)->name('reception.home');

    // Tickets imprimables : une vue dediee, sans navigation, declenchee par
    // window.print() cote navigateur.
    Route::get('/reception/ticket/patient/{visit}', [PrintTicketController::class, 'patient'])
        ->name('reception.ticket.patient');

    Route::get('/reception/ticket/visiteur/{visitor}', [PrintTicketController::class, 'visitor'])
        ->name('reception.ticket.visitor');
});

// La caisse est un role a part entiere : les medecins n'encaissent jamais.
Route::middleware(['auth', 'role.scope:'.Roles::CASHIER])->group(function () {
    Route::get('/caisse', CaisseController::class)->name('caisse.home');

    Route::get('/caisse/recus/{payment}', CaisseReceiptController::class)->name('caisse.receipt');
});

Route::middleware(['auth', 'role.scope:'.Roles::DOCTOR])->group(function () {
    Route::get('/service', ServiceController::class)->name('service.home');

    Route::get('/service/pieces-jointes/{attachment}', ServiceAttachmentDownloadController::class)
        ->name('service.attachment');

    Route::get('/service/ordonnances/{prescription}/pdf', PrescriptionPdfController::class)
        ->name('service.prescription.pdf');

    // Rendus imprimables : le navigateur declenche l'impression, pas le serveur.
    Route::get('/service/pieces-jointes/{attachment}/impression', [PrintableController::class, 'attachment'])
        ->name('service.attachment.print');

    Route::get('/service/ordonnances/{prescription}/impression', [PrintableController::class, 'prescription'])
        ->name('service.prescription.print');
});

/*
|--------------------------------------------------------------------------
| Interfaces generiques des types de personnel (v3.2.1, point 10)
|--------------------------------------------------------------------------
|
| UNE seule route, jamais une route par type : le slug est une valeur lue en
| base, pas un chemin declare a la volee, sans quoi `route:cache` ne verrait
| rien en production.
|
| `role.scope:staff` compare le type du compte connecte au slug demande, avec
| la meme redirection propre que pour les quatre roles fixes.
|
*/
Route::middleware(['auth', 'role.scope:staff'])->group(function () {
    Route::get('/staff/{slug}', StaffInterfaceController::class)->name('staff.home');

    Route::get('/staff/{slug}/ticket/{visit}', StaffPrintTicketController::class)
        ->name('staff.ticket');
});

/*
|--------------------------------------------------------------------------
| Dossier medical complet (module keneya/dme, v3.3.0)
|--------------------------------------------------------------------------
|
| Une seule route, et c'est un passage : elle relie le patient WorkFlow a son
| dossier du DME puis redirige vers le module, sous la session deja ouverte.
|
| Volontairement hors des groupes `role.scope` : l'acces au dossier medical
| est une capacite de type de personnel, pas un role. Un medecin comme un
| type generique peuvent la porter, et le controleur verifie la capacite
| elle-meme : un compte sans elle est renvoye vers son espace.
|
*/
Route::middleware('auth')->group(function () {
    Route::get('/dossier-medical/{patient}', DmeRecordController::class)
        ->name('dossier-medical.ouvrir');
});

/*
|--------------------------------------------------------------------------
| Verification publique d'un document par QR code (module keneya/dme)
|--------------------------------------------------------------------------
|
| Chaque PDF genere par le module porte un QR code qui pointe ici (voir
| PdfGenerator::payload() dans keneya-dme_mod). Volontairement hors des
| routes du module : celles-ci sont chargees sous le prefixe `dme` et
| derriere une session authentifiee (`dme.access`), alors que ce lien doit
| rester vrai sans connexion et a l'adresse exacte imprimee sur le document,
| /documents/verifier/{reference}.
|
| Public et sans authentification, comme le portail patient : la personne
| qui scanne n'a droit qu'a la preuve que la reference existe, jamais au
| contenu medical. Le `throttle` limite le rythme des verifications
| automatisees sans genant la lecture normale d'un QR code.
|
*/
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/documents/verifier/{reference}', DocumentVerificationController::class)
        ->name('documents.verifier');
});

// Affichage public en salle d'attente (moniteur mural, sans connexion).
Route::get('/board', BoardController::class)->name('board');

/*
|--------------------------------------------------------------------------
| Portail patient
|--------------------------------------------------------------------------
|
| Public et sans authentification : c'est le lien lui-meme, porteur d'un UUID
| non devinable, qui tient lieu d'adresse. Le contenu reste masque tant que le
| code a quatre chiffres n'a pas ete valide.
|
| Le lien ne perime jamais, d'ou le `throttle` : il limite les tentatives
| automatisees en complement du verrouillage par dossier.
|
*/
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/mes-documents/{token}', PatientPortalController::class)->name('portal.show');

    Route::get('/mes-documents/{token}/piece-jointe/{attachment}', PortalDownloadController::class)
        ->name('portal.attachment');

    Route::get('/mes-documents/{token}/ordonnance/{prescription}/pdf', PortalPrescriptionPdfController::class)
        ->name('portal.prescription.pdf');
});

/*
|--------------------------------------------------------------------------
| Page de retour d'un visiteur (v3.2.8, point 4)
|--------------------------------------------------------------------------
|
| Publique elle aussi, mais sans code a quatre chiffres : un visiteur n'a pas
| de dossier medical a proteger, et rien de medical ne s'affiche ici. Seul le
| jeton de l'URL, un UUID, jamais le `visitor_code` ni l'id, tient lieu
| d'adresse.
|
| Le `throttle` est plus large que celui du portail patient, la page etant
| moins sensible, mais il reste indispensable : un identifiant long n'est pas
| une raison de laisser essayer indefiniment.
|
*/
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/mon-avis/{token}', VisitorFeedbackController::class)->name('feedback.visitor');
});
