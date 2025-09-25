<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\RendezVousController;

//routes des patients
Route::get('/patients', [PatientController::class, 'index']);
Route::post('/patients', [PatientController::class, 'store']);
Route::get('/patients/{id}', [PatientController::class, 'show']);
Route::put('/patients/{id}', [PatientController::class, 'update']);
Route::delete('/patients/{id}', [PatientController::class, 'destroy']);
// dossier patient complet
Route::get('/patients/{id}/dossier', [PatientController::class, 'showDossier']);


// routes des rdv
Route::get('/rendezvous', [RendezVousController::class, 'index']);
Route::post('/rendezvous', [RendezVousController::class, 'store']);
Route::get('/rendezvous/{id}', [RendezVousController::class, 'show']);
Route::put('/rendezvous/{id}', [RendezVousController::class, 'update']);
Route::delete('/rendezvous/{id}', [RendezVousController::class, 'destroy']);
