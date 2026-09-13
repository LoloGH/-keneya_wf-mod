<?php

declare(strict_types=1);

use Keneya\Dme\Http\Controllers\Api\AuthApiController;
use Keneya\Dme\Http\Controllers\Api\PatientApiController;
use Keneya\Dme\Http\Controllers\Api\PatientRecordApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST de Keneya-DME (§43)
|--------------------------------------------------------------------------
|
| L'API est authentifiée par jeton (Laravel Sanctum) et soumise aux mêmes
| policies que l'interface web : un jeton n'accorde jamais plus que le
| rôle de son porteur. Les représentations suivent la structure des
| ressources FHIR correspondantes (§44), sans prétendre à la conformité
| complète en phase 1.
|
| Aucun chemin de fichier n'est exposé : les documents ne sont
| accessibles que par la route de téléchargement contrôlée (§42).
|
*/

Route::post('/auth/token', [AuthApiController::class, 'token'])
    ->middleware('throttle:dme-login')
    ->name('api.auth.token');

Route::middleware(['auth:sanctum', 'throttle:dme-api'])->group(function (): void {

    Route::get('/auth/me', [AuthApiController::class, 'me'])->name('api.auth.me');
    Route::delete('/auth/token', [AuthApiController::class, 'revoke'])->name('api.auth.revoke');

    // Patients
    Route::get('/patients', [PatientApiController::class, 'index'])->name('api.patients.index');
    Route::post('/patients', [PatientApiController::class, 'store'])->name('api.patients.store');
    Route::get('/patients/{patient}', [PatientApiController::class, 'show'])->name('api.patients.show');
    Route::put('/patients/{patient}', [PatientApiController::class, 'update'])->name('api.patients.update');

    // Sous-ressources du dossier
    Route::get('/patients/{patient}/consultations', [PatientRecordApiController::class, 'consultations'])
        ->name('api.patients.consultations');
    Route::post('/patients/{patient}/consultations', [PatientRecordApiController::class, 'storeConsultation'])
        ->name('api.patients.consultations.store');

    Route::get('/patients/{patient}/prescriptions', [PatientRecordApiController::class, 'prescriptions'])
        ->name('api.patients.prescriptions');
    Route::post('/patients/{patient}/prescriptions', [PatientRecordApiController::class, 'storePrescription'])
        ->name('api.patients.prescriptions.store');

    Route::get('/patients/{patient}/laboratory', [PatientRecordApiController::class, 'laboratory'])
        ->name('api.patients.laboratory');
    Route::post('/laboratory/orders', [PatientRecordApiController::class, 'storeLabOrder'])
        ->name('api.laboratory.orders.store');

    Route::get('/patients/{patient}/documents', [PatientRecordApiController::class, 'documents'])
        ->name('api.patients.documents');
    Route::post('/documents', [PatientRecordApiController::class, 'storeDocument'])
        ->name('api.documents.store');
});
