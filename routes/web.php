<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PatientController;
Route::get('/', function () {
    return view('welcome');
});

// Route::get('/patients', [PatientController::class, 'index']);
// Route::post('/patients', [PatientController::class, 'store']);
// Route::get('/patients/{id}', [PatientController::class, 'show']);
// Route::put('/patients/{id}', [PatientController::class, 'update']);
// Route::delete('/patients/{id}', [PatientController::class, 'destroy']);




// use App\Http\Controllers\UserController;
// use App\Http\Controllers\ProfileController;
// use App\Http\Controllers\SuperAdminController;
// use App\Http\Controllers\AdminStructureController;
// use App\Http\Controllers\StructureController;
// use App\Http\Controllers\AssistantController;
// use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware(['auth', 'verified'])->group(function () {
//     // Profile routes
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

//     // Dashboard route
//     Route::get('/dashboard', function () {
//         return view('dashboard');
//     })->name('dashboard');
    
//     // Routes communes de gestion des utilisateurs
//     Route::prefix('users')->group(function () {
//         Route::get('/', [UserController::class, 'index'])->name('users.index');
//         Route::get('/create', [UserController::class, 'create'])->name('users.create');
//         Route::post('/', [UserController::class, 'store'])->name('users.store');
//         Route::get('/{user}/edit', [UserController::class, 'edit'])->name('users.edit'); // Route manquante ajoutée
//         Route::put('/{user}', [UserController::class, 'update'])->name('users.update'); // Route manquante ajoutée
//         Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy'); // Route manquante ajoutée
//         Route::post('/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        
        
//     });

//     // Routes pour Super Admin

//         // Gestion des administrateurs
       
// Route::prefix('users/admins')->group(function () {
//     Route::get('/', [SuperAdminController::class, 'listAdmins'])->name('super_admin.users.admins');
//     Route::get('/create', [SuperAdminController::class, 'createAdmin'])->name('admins.create');
//     Route::post('/', [SuperAdminController::class, 'storeAdmin'])->name('admins.store');
//     Route::get('/{admin}/edit', [SuperAdminController::class, 'editAdmin'])->name('admins.edit');
//     Route::delete('/{admin}', [SuperAdminController::class, 'destroyAdmin'])->name('admins.destroy');
//     Route::post('/{admin}/toggle', [SuperAdminController::class, 'toggleAdmin'])->name('admins.toggle');
//     Route::delete('/{user}', [SuperAdminController::class, 'destroyAdmin'])->name('admins.destroy');
// });

//         // Gestion des structures
//         Route::prefix('structures')->group(function () {
//             Route::get('/', [StructureController::class, 'index'])->name('structures.index');
//             Route::get('/create', [StructureController::class, 'create'])->name('structures.create');
//             Route::post('/', [StructureController::class, 'store'])->name('structures.store');
//             Route::get('/{structure}/edit', [StructureController::class, 'edit'])->name('structures.edit');
//             Route::put('/{structure}', [StructureController::class, 'update'])->name('structures.update');
//             Route::delete('/{structure}', [StructureController::class, 'destroy'])->name('structures.destroy');
//             Route::post('/{structure}/toggle', [StructureController::class, 'toggle'])->name('structures.toggle');
//         });
//     });

//     // Routes pour Admin Structure

//         // Dashboard admin structure
//         Route::get('/dashboard', [AdminStructureController::class, 'dashboard'])->name('admin.dashboard');
        
//         // Assistants
//         Route::prefix('users/assistants')->group(function () {
//             Route::get('/', [AdminStructureController::class, 'listAssistants'])->name('admin.users.assistants');
//             Route::get('/create', [AdminStructureController::class, 'createAssistant'])->name('admin.assistants.create');
//             Route::post('/', [AdminStructureController::class, 'storeAssistant'])->name('admin.assistants.store');
//             Route::get('/{assistant}', [AdminStructureController::class, 'showAssistant'])->name('admin.assistants.show');
//             Route::patch('/{assistant}/toggle', [AdminStructureController::class, 'toggleAssistant'])->name('admin.assistants.toggle');
        
        
//         // Médecins
//         Route::prefix('users/medecins')->group(function () {
//             Route::get('/', [AdminStructureController::class, 'listMedecins'])->name('admin.users.medecins');
//             Route::get('/create', [AdminStructureController::class, 'createMedecin'])->name('admin.medecins.create');
//             Route::post('/', [AdminStructureController::class, 'storeMedecin'])->name('admin.medecins.store');
//             Route::get('/{medecin}', [AdminStructureController::class, 'showMedecin'])->name('admin.medecins.show');
//             Route::patch('/{medecin}/toggle', [AdminStructureController::class, 'toggleMedecin'])->name('admin.medecins.toggle');

//         });
        
//         // Patients
//         Route::prefix('users/patients')->group(function () {
//             Route::get('/', [AdminStructureController::class, 'listPatients'])->name('admin.users.patients');
//             Route::get('/create', [AdminStructureController::class, 'createPatient'])->name('admin.patients.create');
//             Route::post('/', [AdminStructureController::class, 'storePatient'])->name('admin.patients.store');
//         });
//     });

//     // Routes pour Assistant
//     Route::middleware(['assistant'])->prefix('assistant')->group(function () {
//         Route::get('/users/patients', [AssistantController::class, 'listPatients'])->name('assistant.users.patients');
//         // Ajoutez d'autres routes pour assistant ici si nécessaire
//     });


// require __DIR__.'/auth.php';

