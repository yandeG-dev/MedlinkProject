<?php

use App\Http\Controllers\Auth\AuthApiController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\AdminStructureController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\MedecinController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PatientController; // Ajouter cette ligne

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Test route pour vérifier que l'API fonctionne
Route::get('/test', function () {
    return response()->json(['message' => 'API fonctionne correctement']);
});

// Routes API publiques (sans authentification)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthApiController::class, 'register'])->name('api.auth.register');
    Route::post('/login', [AuthApiController::class, 'login'])->name('api.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthApiController::class, 'logout'])->name('api.auth.logout');
    });

        // Réinitialisation de mot de passe
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword'])->name('api.auth.forgot-password');
    Route::post('/reset-password', [AuthApiController::class, 'resetPassword'])->name('api.auth.reset-password');
    Route::post('/verify-token', [AuthApiController::class, 'verifyToken'])->name('api.auth.verify-token');
      // Changer le mot de passe quand connecté
        Route::post('/change-password', [AuthApiController::class, 'changePassword'])->name('api.auth.change-password');
        Route::post('/update-profile', [AuthApiController::class, 'updateProfile'])->name('api.auth.update-profile');

});

// Profile utilisateur
Route::get('/user', function (Request $request) {
    return response()->json([
        'success' => true,
        'data' => $request->user()
    ]);
})->name('api.user');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::delete('/', [ProfileController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('super-admin')->group(function () {
        // Tableau de bord complet
        Route::get('/dashboard-complet', [SuperAdminController::class, 'dashboardComplet']);
        
        // Gestion des structures
        Route::get('/structures', [SuperAdminController::class, 'listStructures']);
        Route::post('/structures-complete', [SuperAdminController::class, 'storeStructureComplete']);
        Route::get('/structures/{structure}', [SuperAdminController::class, 'showStructure']);
        Route::put('/structures/{structure}/toggle', [SuperAdminController::class, 'toggleStructure']);
        
        // Gestion globale des utilisateurs (routes fusionnées)
        Route::get('/utilisateurs', [SuperAdminController::class, 'listUtilisateursGlobaux']);
        Route::post('/utilisateurs', [SuperAdminController::class, 'storeUtilisateurGlobal']);
        Route::get('/utilisateurs/{user}', [SuperAdminController::class, 'showUtilisateur']);
        Route::put('/utilisateurs/{user}', [SuperAdminController::class, 'updateUtilisateur']);
        Route::delete('/utilisateurs/{user}', [SuperAdminController::class, 'destroyUtilisateur']);
        Route::put('/utilisateurs/{id}/toggle', [SuperAdminController::class, 'toggleUtilisateurGlobal']);
        
        // Statistiques spécifiques aux admins
        Route::get('/utilisateurs/{admin}/stats', [SuperAdminController::class, 'adminStats']);
        
        // Statistiques et rapports
        Route::get('/stats-structures', [SuperAdminController::class, 'statsParStructure']);
        Route::get('/rapports-activite', [SuperAdminController::class, 'rapportsActivite']);
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('admin-structure')->group(function () {
        // =============================================
        // DASHBOARD ET STATISTIQUES
     
        //Route::get('/dashboard', [AdminStructureController::class, 'dashboard']);
        Route::get('/dashboard-complet', [AdminStructureController::class, 'dashboardComplet']);
        
        // =============================================
        // GESTION DE LA STRUCTURE
    
        Route::get('/check-structure', [AdminStructureController::class, 'checkStructure']);
        Route::post('/structure', [AdminStructureController::class, 'storeStructure']);
        Route::get('/structure', [AdminStructureController::class, 'showStructure']);
        Route::put('/structure', [AdminStructureController::class, 'updateStructure']);
        
        // =============================================
        // GESTION DES UTILISATEURS (ROUTES FUSIONNÉES)
        // =============================================
        
        // Route universelle pour lister tous les utilisateurs (avec filtres)
        Route::get('/utilisateurs', [AdminStructureController::class, 'listUtilisateursStructure']);
        
        // Route universelle pour créer n'importe quel type d'utilisateur
        Route::post('/utilisateurs', [AdminStructureController::class, 'storeUtilisateur']);
        
        // Route universelle pour voir les détails d'un utilisateur
        Route::get('/utilisateurs/{utilisateur}', [AdminStructureController::class, 'showUtilisateur']);
        
        // Route universelle pour activer/désactiver un utilisateur
        Route::put('/utilisateurs/{utilisateur}/toggle', [AdminStructureController::class, 'toggleUtilisateur']);
        
        // =============================================
        // GESTION DES PATIENTS (ROUTES SPÉCIFIQUES)
        // =============================================
        Route::get('/patients', [AdminStructureController::class, 'listPatients']);
        Route::post('/patients', [AdminStructureController::class, 'storePatient']);
        
        // =============================================
        // GESTION DES ACTIVITÉS
        // =============================================
        Route::get('/prescriptions', [AdminStructureController::class, 'listPrescriptionsStructure']);
        Route::get('/consultations', [AdminStructureController::class, 'listConsultationsStructure']);
        Route::get('/rendez-vous', [AdminStructureController::class, 'listRendezVousStructure']);
        
        // =============================================
        // STATISTIQUES DÉTAILLÉES
        // =============================================
        Route::get('/stats-medecins', [AdminStructureController::class, 'statsParMedecin']);
        
        // =============================================
        // UTILITAIRES
        // =============================================
        Route::get('/assistants-for-assignment', [AdminStructureController::class, 'getAssistantsForAssignment']);
    });

        // ROUTES STRUCTURES (pour tous les utilisateurs autorisés)
    Route::prefix('structures')->group(function () {
        Route::get('/', [StructureController::class, 'index']);
        Route::post('/', [StructureController::class, 'store']);
        Route::get('/{structure}', [StructureController::class, 'show']);
        Route::put('/{structure}', [StructureController::class, 'update']);
        Route::patch('/{structure}/toggle', [StructureController::class, 'toggle']);
        Route::delete('/{structure}', [StructureController::class, 'destroy']);
    });
});




Route::middleware(['auth:sanctum'])->group(function () {
    // Patients
    Route::get('/medecin/patients', [MedecinController::class, 'listPatient']);
    Route::get('/medecin/patients/{patient}', [MedecinController::class, 'showPatient']);
    
    // Consultations
    Route::post('/medecin/patients/{patient}/consultations', [MedecinController::class, 'storeConsultation']);
    Route::get('/medecin/consultations/{consultation}', [MedecinController::class, 'showConsultation']);
    
    // Rendez-vous
    Route::get('/medecin/rendez-vous', [MedecinController::class, 'listRendezVous']);
    Route::get('/medecin/rendez-vous/patients', [MedecinController::class, 'getPatientsForRendezVous']);
    Route::post('/medecin/rendez-vous', [MedecinController::class, 'storeRendezVous']);
    Route::put('/medecin/rendez-vous/{rendezVous}/cancel', [MedecinController::class, 'cancelRendezVous']);
    Route::put('/medecin/rendez-vous/{rendezVous}/complete', [MedecinController::class, 'completeRendezVous']); 
    // Dashboard et agenda
    // Route::get('/medecin/dashboard', [MedecinController::class, 'dashboard']);
    Route::get('/medecin/agenda', [MedecinController::class, 'agenda']);
      Route::get('/medecin/dashboard', [MedecinController::class, 'dashboard']);
    Route::get('/medecin/dashboard/charts', [MedecinController::class, 'dashboardCharts']);
      Route::get('/prescriptions', [MedecinController::class, 'listPrescriptions']);
    Route::get('/consultations', [MedecinController::class, 'listConsultations']);
    Route::put('/consultations/{consultation}', [MedecinController::class, 'updateConsultation']);
    Route::put('/rendez-vous/{rendezVous}/annuler', [MedecinController::class, 'annulerRendezVous']);
    

});

Route::middleware(['auth:sanctum'])->group(function () {
        // Dashboard
    Route::get('/assistant/dashboard', [AssistantController::class, 'dashboard']);
    // Patients
    Route::get('/assistant/patients', [AssistantController::class, 'listPatients']);
    Route::post('/assistant/patients', [AssistantController::class, 'storePatient']);
    Route::get('/assistant/patients/{patient}', [AssistantController::class, 'showPatient']);
    Route::put('/assistant/patients/{patient}', [AssistantController::class, 'updatePatient']);
    Route::delete('/assistant/patients/{patient}', [AssistantController::class, 'destroyPatient']);
    
    // Médecins
    Route::get('/assistant/medecins', [AssistantController::class, 'listMedecins']);
    Route::post('/assistant/medecins', [AssistantController::class, 'storeMedecin']);
    Route::get('/assistant/medecins/{medecin}', [AssistantController::class, 'showMedecin']);
    Route::put('/assistant/medecins/{medecin}', [AssistantController::class, 'updateMedecin']);
    Route::delete('/assistant/medecins/{medecin}', [AssistantController::class, 'destroyMedecin']);

    Route::get('/prescriptions-structure', [AssistantController::class, 'listPrescriptionsStructure']);
    Route::get('/consultations-structure', [AssistantController::class, 'listConsultationsStructure']);
    Route::get('/rendez-vous-structure', [AssistantController::class, 'listRendezVousStructure']);
    Route::put('/rendez-vous/{rendezVous}/gerer', [AssistantController::class, 'gererRendezVous']);
    

});

// Gestion des utilisateurs
Route::apiResource('users', UserController::class)->except(['create', 'edit']);
Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('api.users.toggle');

// Gestion des structures (super admin seulement)
Route::middleware(['super_admin'])->group(function () {
    Route::apiResource('structures', StructureController::class)->except(['create', 'edit']);
    Route::post('structures/{structure}/toggle', [StructureController::class, 'toggle'])->name('api.structures.toggle');
});


// Routes patient

    Route::middleware(['auth:sanctum'])->prefix('patient')->group(function (){
    // Dashboard
    Route::get('/dashboard', [PatientController::class, 'dashboard']);
    
    // Prescriptions
    Route::get('/prescriptions', [PatientController::class, 'mesPrescriptions']);
    Route::get('/prescriptions/{prescription}', [PatientController::class, 'voirPrescription']);
    Route::get('/prescriptions/{prescription}/telecharger', [PatientController::class, 'telechargerPrescription']);
    Route::get('/prescriptions/rechercher', [PatientController::class, 'rechercherPrescriptions']);
    
    // Rendez-vous
    Route::get('/rendez-vous', [PatientController::class, 'mesRendezVous']);
    Route::post('/rendez-vous', [PatientController::class, 'prendreRendezVous']);
    Route::put('/rendez-vous/{rendezVous}/annuler', [PatientController::class, 'annulerRendezVous']);
    Route::get('/rendez-vous/{rendezVous}', [PatientController::class, 'voirRendezVous']);
    
    // Consultations
    Route::get('/consultations', [PatientController::class, 'mesConsultations']);
    Route::get('/consultations/{consultation}', [PatientController::class, 'voirConsultation']);
    
    // Médecins de la structure
    Route::get('/medecins-structure', [PatientController::class, 'medecinsDeMaStructure']);
    
    // Informations médicales
    Route::get('/informations-medicales', [PatientController::class, 'mesInformationsMedicales']);
    Route::put('/informations-medicales', [PatientController::class, 'mettreAJourInformationsMedicales']);
    });
