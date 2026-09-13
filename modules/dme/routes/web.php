<?php

declare(strict_types=1);

use Keneya\Dme\Http\Controllers\Web\AppointmentController;
use Keneya\Dme\Http\Controllers\Web\AuditController;
use Keneya\Dme\Http\Controllers\Web\Auth\LoginController;
use Keneya\Dme\Http\Controllers\Web\ConsultationController;
use Keneya\Dme\Http\Controllers\Web\DashboardController;
use Keneya\Dme\Http\Controllers\Web\DocumentController;
use Keneya\Dme\Http\Controllers\Web\HospitalizationController;
use Keneya\Dme\Http\Controllers\Web\ImagingController;
use Keneya\Dme\Http\Controllers\Web\LaboratoryController;
use Keneya\Dme\Http\Controllers\Web\NotificationController;
use Keneya\Dme\Http\Controllers\Web\CareOrderController;
use Keneya\Dme\Http\Controllers\Web\NursingController;
use Keneya\Dme\Http\Controllers\Web\PatientController;
use Keneya\Dme\Http\Controllers\Web\PatientRecordController;
use Keneya\Dme\Http\Controllers\Web\PrescriptionController;
use Keneya\Dme\Http\Controllers\Web\SearchController;
use Keneya\Dme\Http\Controllers\Web\RolePermissionController;
use Keneya\Dme\Http\Controllers\Web\ServiceController;
use Keneya\Dme\Http\Controllers\Web\SettingsController;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Sms\Pipeline\Http\SmsPipelineController;
use Keneya\Dme\Sms\Pipeline\QueuedSmsDispatcher;
use Keneya\Dme\Http\Controllers\Web\UserController;
use Keneya\Dme\Standalone\StandaloneMode;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes web du module Keneya-DME
|--------------------------------------------------------------------------
|
| Ce fichier est chargé par DmeServiceProvider à l'intérieur d'un groupe
| qui porte le préfixe d'URL du module, le préfixe de nom `dme.` et
| l'intergiciel `dme.access` : aucune de ces routes n'est atteignable sans
| que l'application hôte ait accordé l'accès au dossier médical.
|
| Toutes les routes exigent en outre une session authentifiée, fournie par
| l'hôte. Les autorisations fines restent portées par les policies (§32).
|
| Aucun fichier n'est servi depuis le système de fichiers : les documents
| passent par documents.download, contrôlé par MedicalDocumentPolicy (§42).
|
*/

/*
| Authentification locale : développement uniquement.
|
| En fonctionnement normal, le module s'appuie sur la session déjà
| authentifiée par l'application hôte : il n'expose ni page de connexion,
| ni déconnexion. Ces routes n'existent que sous DME_STANDALONE_DEV, pour
| que le module reste navigable tant qu'aucun hôte ne le porte.
*/
if (app(StandaloneMode::class)->enabled()) {
    Route::middleware('guest')->group(function (): void {
        Route::get('/connexion', [LoginController::class, 'show'])->name('login');
        Route::post('/connexion', [LoginController::class, 'store'])
            ->middleware('throttle:dme-login')
            ->name('login.attempt');
    });

    Route::post('/deconnexion', [LoginController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');
}

Route::middleware('auth')->group(function (): void {

    Route::get('/', fn () => redirect()->route('dme.dashboard'))->name('home');
    Route::get('/tableau-de-bord', DashboardController::class)->name('dashboard');

    Route::get('/recherche', SearchController::class)->name('search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/lue', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::post('/notifications/tout-lire', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');

    // ---------------------------------------------------------------
    // Patients et dossier médical électronique (§11-14)
    // ---------------------------------------------------------------
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/nouveau', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/patients/export', [PatientController::class, 'export'])->name('patients.export');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::get('/patients/{patient}/modifier', [PatientController::class, 'edit'])->name('patients.edit');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::get('/patients/{patient}/fiche.pdf', [PatientController::class, 'summaryPdf'])
        ->name('patients.summary-pdf');

    // Archiver range un dossier sans rien detruire, et se defait. Supprimer
    // detruit le dossier et tout son contenu clinique : verbe HTTP distinct,
    // permission distincte, et un dossier deja archive pour seul point de
    // depart, on ne detruit pas un dossier actif d'un seul clic.
    Route::patch('/patients/{patient}/archiver', [PatientController::class, 'archive'])
        ->name('patients.archive');
    Route::patch('/patients/{patient}/restaurer', [PatientController::class, 'restore'])
        ->name('patients.restore');
    Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])
        ->name('patients.destroy');

    // Enregistrements du dossier saisis depuis les onglets du DME
    Route::prefix('/patients/{patient}')->name('record.')->group(function (): void {
        Route::post('/antecedents', [PatientRecordController::class, 'storeHistory'])->name('histories.store');
        Route::post('/allergies', [PatientRecordController::class, 'storeAllergy'])->name('allergies.store');
        Route::post('/pathologies', [PatientRecordController::class, 'storeCondition'])->name('conditions.store');
        Route::post('/medicaments', [PatientRecordController::class, 'storeMedication'])->name('medications.store');
        Route::post('/constantes', [PatientRecordController::class, 'storeVitalSign'])->name('vitals.store');
        Route::post('/contacts-urgence', [PatientRecordController::class, 'storeEmergencyContact'])
            ->name('contacts.store');
    });

    // ---------------------------------------------------------------
    // Consultations (§19)
    // ---------------------------------------------------------------
    Route::get('/consultations', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::get('/patients/{patient}/consultations/nouvelle', [ConsultationController::class, 'create'])
        ->name('consultations.create');
    Route::post('/patients/{patient}/consultations', [ConsultationController::class, 'store'])
        ->name('consultations.store');
    Route::get('/consultations/{consultation}', [ConsultationController::class, 'show'])
        ->name('consultations.show');
    Route::get('/consultations/{consultation}/modifier', [ConsultationController::class, 'edit'])
        ->name('consultations.edit');
    Route::put('/consultations/{consultation}', [ConsultationController::class, 'update'])
        ->name('consultations.update');
    Route::post('/consultations/{consultation}/terminer', [ConsultationController::class, 'complete'])
        ->name('consultations.complete');
    Route::get('/consultations/{consultation}/compte-rendu.pdf', [ConsultationController::class, 'reportPdf'])
        ->name('consultations.report-pdf');

    // ---------------------------------------------------------------
    // Ordonnances (§22)
    // ---------------------------------------------------------------
    Route::get('/ordonnances', [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/patients/{patient}/ordonnances/nouvelle', [PrescriptionController::class, 'create'])
        ->name('prescriptions.create');
    Route::post('/patients/{patient}/ordonnances', [PrescriptionController::class, 'store'])
        ->name('prescriptions.store');
    Route::get('/ordonnances/{prescription}', [PrescriptionController::class, 'show'])
        ->name('prescriptions.show');
    Route::post('/ordonnances/{prescription}/valider', [PrescriptionController::class, 'validatePrescription'])
        ->name('prescriptions.validate');
    Route::post('/ordonnances/{prescription}/delivrer', [PrescriptionController::class, 'dispense'])
        ->name('prescriptions.dispense');
    Route::get('/ordonnances/{prescription}/pdf', [PrescriptionController::class, 'pdf'])
        ->name('prescriptions.pdf');

    // ---------------------------------------------------------------
    // Laboratoire (§23)
    // ---------------------------------------------------------------
    Route::get('/laboratoire', [LaboratoryController::class, 'index'])->name('laboratory.index');
    Route::get('/patients/{patient}/laboratoire/nouvelle', [LaboratoryController::class, 'create'])
        ->name('laboratory.create');
    Route::post('/patients/{patient}/laboratoire', [LaboratoryController::class, 'store'])
        ->name('laboratory.store');
    Route::get('/laboratoire/{labOrder}', [LaboratoryController::class, 'show'])->name('laboratory.show');
    Route::post('/laboratoire/{labOrder}/resultats', [LaboratoryController::class, 'storeResults'])
        ->name('laboratory.results.store');
    Route::post('/laboratoire/{labOrder}/valider', [LaboratoryController::class, 'validateResults'])
        ->name('laboratory.validate');
    Route::get('/laboratoire/{labOrder}/compte-rendu.pdf', [LaboratoryController::class, 'pdf'])
        ->name('laboratory.pdf');

    // ---------------------------------------------------------------
    // Imagerie (§24)
    // ---------------------------------------------------------------
    Route::get('/imagerie', [ImagingController::class, 'index'])->name('imaging.index');
    Route::get('/patients/{patient}/imagerie/nouvelle', [ImagingController::class, 'create'])
        ->name('imaging.create');
    Route::post('/patients/{patient}/imagerie', [ImagingController::class, 'store'])->name('imaging.store');
    Route::get('/imagerie/{imagingOrder}', [ImagingController::class, 'show'])->name('imaging.show');
    Route::post('/imagerie/{imagingOrder}/compte-rendu', [ImagingController::class, 'storeReport'])
        ->name('imaging.report.store');

    // ---------------------------------------------------------------
    // Hospitalisation et soins (§25-26)
    // ---------------------------------------------------------------
    Route::get('/hospitalisations', [HospitalizationController::class, 'index'])->name('hospitalizations.index');
    Route::get('/patients/{patient}/hospitalisations/nouvelle', [HospitalizationController::class, 'create'])
        ->name('hospitalizations.create');
    Route::post('/patients/{patient}/hospitalisations', [HospitalizationController::class, 'store'])
        ->name('hospitalizations.store');
    Route::get('/hospitalisations/{hospitalization}', [HospitalizationController::class, 'show'])
        ->name('hospitalizations.show');
    Route::post('/hospitalisations/{hospitalization}/evenements', [HospitalizationController::class, 'storeEvent'])
        ->name('hospitalizations.events.store');
    Route::post('/hospitalisations/{hospitalization}/sortie', [HospitalizationController::class, 'discharge'])
        ->name('hospitalizations.discharge');
    Route::get('/hospitalisations/{hospitalization}/compte-rendu.pdf', [HospitalizationController::class, 'pdf'])
        ->name('hospitalizations.pdf');

    Route::post('/patients/{patient}/soins', [NursingController::class, 'store'])->name('nursing.store');

    // Soins programmés : la prescription, distincte du soin réalisé.
    Route::post('/patients/{patient}/soins-programmes', [CareOrderController::class, 'store'])
        ->name('care-orders.store');
    Route::patch('/soins-programmes/{careOrder}/attribution', [CareOrderController::class, 'assign'])
        ->name('care-orders.assign');
    Route::patch('/soins-programmes/{careOrder}/realisation', [CareOrderController::class, 'execute'])
        ->name('care-orders.execute');
    Route::patch('/soins-programmes/{careOrder}/annulation', [CareOrderController::class, 'cancel'])
        ->name('care-orders.cancel');

    // ---------------------------------------------------------------
    // Rendez-vous (§27)
    // ---------------------------------------------------------------
    Route::get('/rendez-vous', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/patients/{patient}/rendez-vous', [AppointmentController::class, 'store'])
        ->name('appointments.store');
    Route::get('/rendez-vous/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::patch('/rendez-vous/{appointment}/statut', [AppointmentController::class, 'updateStatus'])
        ->name('appointments.status');

    // ---------------------------------------------------------------
    // Documents (§28, §42)
    // ---------------------------------------------------------------
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/patients/{patient}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/telecharger', [DocumentController::class, 'download'])
        ->name('documents.download');
    Route::get('/documents/{document}/apercu', [DocumentController::class, 'preview'])->name('documents.preview');

    // ---------------------------------------------------------------
    // Administration (§30-31)
    // ---------------------------------------------------------------
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

    Route::get('/utilisateurs', [UserController::class, 'index'])->name('users.index');
    Route::get('/utilisateurs/nouveau', [UserController::class, 'create'])->name('users.create');
    Route::post('/utilisateurs', [UserController::class, 'store'])->name('users.store');
    Route::get('/utilisateurs/{user}/modifier', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/utilisateurs/{user}', [UserController::class, 'update'])->name('users.update');

    // Console du pipeline SMS interne. Elle disparaît dès que l'hôte
    // fournit sa propre implémentation de SmsDispatcherContract : c'est
    // alors à lui qu'appartiennent l'historique et les réessais.
    if (app(SmsDispatcherContract::class) instanceof QueuedSmsDispatcher) {
        Route::get('/sms', [SmsPipelineController::class, 'index'])->name('sms.index');
        Route::post('/sms', [SmsPipelineController::class, 'store'])->name('sms.store');
        Route::post('/sms/{smsMessage}/rejouer', [SmsPipelineController::class, 'retry'])->name('sms.retry');
    }

    Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
    // Son propre compte : ouvert à tous, aucune permission ne le conditionne.
    Route::put('/parametres/mot-de-passe', [SettingsController::class, 'updatePassword'])
        ->name('settings.password.update');
    Route::patch('/parametres/garde', [SettingsController::class, 'toggleDuty'])
        ->name('settings.duty.toggle');
    // Établissement et identifiants : réservés à settings.manage, vérifié dans le contrôleur.
    Route::put('/parametres/etablissement', [SettingsController::class, 'updateFacility'])
        ->name('settings.facility.update');
    Route::put('/parametres/identifiants', [SettingsController::class, 'updateIdentifiers'])
        ->name('settings.identifiers.update');
    // Matrice des rôles : réservée à roles.manage, vérifié dans le contrôleur.
    Route::post('/parametres/roles', [RolePermissionController::class, 'store'])
        ->name('settings.roles.store');
    Route::put('/parametres/roles', [RolePermissionController::class, 'update'])
        ->name('settings.roles.update');
    Route::post('/parametres/roles/reinitialiser', [RolePermissionController::class, 'reset'])
        ->name('settings.roles.reset');
    // Services : réservés à settings.manage, vérifié dans le contrôleur.
    Route::post('/parametres/services', [ServiceController::class, 'store'])
        ->name('settings.services.store');
});
