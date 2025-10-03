<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Prescription;
use App\Models\Consultation;
use App\Models\Rdv;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    /**
     * Afficher le tableau de bord de l'assistant
     */
    public function dashboard(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        try {
            // Statistiques existantes
            $stats = [
                'total_patients' => User::where('role', 'patient')
                                       ->where('createur_id', $assistantId)
                                       ->where('structure_id', $structureId)
                                       ->count(),
                
                'patients_ajoutes_ce_mois' => User::where('role', 'patient')
                                                 ->where('createur_id', $assistantId)
                                                 ->where('structure_id', $structureId)
                                                 ->whereMonth('created_at', now()->month)
                                                 ->whereYear('created_at', now()->year)
                                                 ->count(),
                
                'total_medecins' => User::where('role', 'medecin')
                                       ->where('createur_id', $assistantId)
                                       ->where('structure_id', $structureId)
                                       ->count(),
                
                'medecins_ajoutes_ce_mois' => User::where('role', 'medecin')
                                                 ->where('createur_id', $assistantId)
                                                 ->where('structure_id', $structureId)
                                                 ->whereMonth('created_at', now()->month)
                                                 ->whereYear('created_at', now()->year)
                                                 ->count(),

                // Nouvelles statistiques
                'prescriptions_structure' => Prescription::where('structure_id', $structureId)->count(),
                'consultations_aujourdhui' => Consultation::where('structure_id', $structureId)
                                                         ->whereDate('created_at', today())->count(),
                'rdv_planifies' => Rdv::where('structure_id', $structureId)
                                     ->where('statut', 'planifie')
                                     ->where('date_rdv', '>=', now())->count(),
            ];

            // Prescriptions récentes de la structure
            $prescriptions_recentes = Prescription::with(['medecin', 'patient'])
                ->where('structure_id', $structureId)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            // Consultations récentes de la structure
            $consultations_recentes = Consultation::with(['medecin', 'patient'])
                ->where('structure_id', $structureId)
                ->orderBy('date_consultation', 'desc')
                ->take(5)
                ->get();

            // Rendez-vous à venir
            $rdv_prochains = Rdv::with(['medecin', 'patient'])
                ->where('structure_id', $structureId)
                ->where('statut', 'planifie')
                ->where('date_rdv', '>=', now())
                ->orderBy('date_rdv')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'prescriptions_recentes' => $prescriptions_recentes,
                    'consultations_recentes' => $consultations_recentes,
                    'rdv_prochains' => $rdv_prochains,
                    'patients_recents' => User::where('role', 'patient')
                                             ->where('createur_id', $assistantId)
                                             ->where('structure_id', $structureId)
                                             ->orderBy('created_at', 'desc')
                                             ->take(5)
                                             ->get(['id', 'nom', 'prenom', 'email', 'created_at']),
                    'medecins_recents' => User::where('role', 'medecin')
                                             ->where('createur_id', $assistantId)
                                             ->where('structure_id', $structureId)
                                             ->orderBy('created_at', 'desc')
                                             ->take(5)
                                             ->get(['id', 'nom', 'prenom', 'email', 'specialite', 'created_at'])
                ],
                'message' => 'Tableau de bord récupéré avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération du tableau de bord',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gérer les rendez-vous (annuler ou mettre en attente)
     */
    public function gererRendezVous(Request $request, Rdv $rendezVous): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        // Vérifier que le RDV appartient à la structure de l'assistant
        if ($rendezVous->structure_id !== auth()->user()->structure_id) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $request->validate([
            'action' => 'required|in:annuler,en_attente',
            'raison' => 'nullable|string|max:500'
        ]);

        try {
            $nouveauStatut = $request->action === 'annuler' ? 'annule' : 'en_attente';
            
            $rendezVous->update([
                'statut' => $nouveauStatut,
                'raison_annulation' => $request->raison
            ]);

            // TODO: Envoyer email au patient et médecin
            // $this->envoyerNotificationRDV($rendezVous, $nouveauStatut);

            return response()->json([
                'success' => true,
                'message' => "Rendez-vous {$request->action} avec succès"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la gestion du rendez-vous'
            ], 500);
        }
    }

    /**
     * Lister les prescriptions de la structure
     */
    public function listPrescriptionsStructure(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structureId = auth()->user()->structure_id;

        $prescriptions = Prescription::with(['medecin', 'patient'])
            ->where('structure_id', $structureId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $prescriptions
        ]);
    }

    /**
     * Lister les consultations de la structure
     */
    public function listConsultationsStructure(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structureId = auth()->user()->structure_id;

        $consultations = Consultation::with(['medecin', 'patient'])
            ->where('structure_id', $structureId)
            ->orderBy('date_consultation', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $consultations
        ]);
    }

    /**
     * Lister les rendez-vous de la structure
     */
    public function listRendezVousStructure(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structureId = auth()->user()->structure_id;

        $rendezVous = Rdv::with(['medecin', 'patient'])
            ->where('structure_id', $structureId)
            ->orderBy('date_rdv', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $rendezVous
        ]);
    }

    /**
     * Afficher la liste des patients créés par cet assistant
     */
    public function listPatients(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        $patients = User::where('role', 'patient')
                       ->where('createur_id', $assistantId)
                       ->where('structure_id', $structureId)
                       ->orderBy('nom')
                       ->get();

        return response()->json(['patients' => $patients]);
    }

    /**
     * Enregistrer un nouveau patient
     */
    public function storePatient(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'age' => 'required|integer|min:0',
            'adresse' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'antecedants' => 'nullable|string',
            'allergies' => 'nullable|string',
            'groupe_sanguin' => 'nullable|string|max:10',
        ]);

        // Générer un mot de passe par défaut
        $passwordDefault = 'patient123';

        $patient = User::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($passwordDefault),
            'role' => 'patient',
            'age' => $request->age,
            'adresse' => $request->adresse,
            'telephone' => $request->telephone,
            'antecedants' => $request->antecedants ?? '',
            'allergies' => $request->allergies ?? '',
            'createur_id' => auth()->id(),
            'groupe_sanguin' => $request->groupe_sanguin ?? '',
            'structure_id' => auth()->user()->structure_id,
            'actif' => true,
        ]);

        return response()->json([
            'message' => 'Patient créé avec succès',
            'patient' => $patient,
            'password_default' => $passwordDefault // À communiquer au patient
        ], 201);
    }

    /**
     * Afficher les détails d'un patient
     */
    public function showPatient(User $patient): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        // Vérifier que le patient appartient à cet assistant et à la même structure
        if ($patient->createur_id !== $assistantId || $patient->structure_id !== $structureId || $patient->role !== 'patient') {
            return response()->json(['error' => 'Accès non autorisé à ce patient'], 403);
        }

        return response()->json(['patient' => $patient]);
    }

    /**
     * Mettre à jour un patient
     */
    public function updatePatient(Request $request, User $patient): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        // Vérifier que le patient appartient à cet assistant et à la même structure
        if ($patient->createur_id !== $assistantId || $patient->structure_id !== $structureId || $patient->role !== 'patient') {
            return response()->json(['error' => 'Accès non autorisé à ce patient'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email,' . $patient->id,
            'age' => 'required|integer|min:0',
            'adresse' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'antecedants' => 'nullable|string',
            'allergies' => 'nullable|string',
            'groupe_sanguin' => 'nullable|string|max:10',
        ]);

        $patient->update([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'age' => $request->age,
            'adresse' => $request->adresse,
            'telephone' => $request->telephone,
            'antecedants' => $request->antecedants ?? '',
            'allergies' => $request->allergies ?? '',
            'groupe_sanguin' => $request->groupe_sanguin ?? '',
        ]);

        return response()->json([
            'message' => 'Patient mis à jour avec succès',
            'patient' => $patient
        ]);
    }

    /**
     * Supprimer un patient
     */
    public function destroyPatient(User $patient): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        // Vérifier que le patient appartient à cet assistant et à la même structure
        if ($patient->createur_id !== $assistantId || $patient->structure_id !== $structureId || $patient->role !== 'patient') {
            return response()->json(['error' => 'Accès non autorisé à ce patient'], 403);
        }

        $patient->delete();

        return response()->json(['message' => 'Patient supprimé avec succès']);
    }

    /**
     * Afficher la liste des médecins créés par cet assistant
     */
    public function listMedecins(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        $medecins = User::where('role', 'medecin')
                       ->where('createur_id', $assistantId)
                       ->where('structure_id', $structureId)
                       ->orderBy('nom')
                       ->get();

        return response()->json(['medecins' => $medecins]);
    }

    /**
     * Enregistrer un nouveau médecin
     */
    public function storeMedecin(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
            'specialite' => 'required|string|max:100',
        ]);

        $medecin = User::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'medecin',
            'actif' => true,
            'createur_id' => auth()->id(),
            'specialite' => $request->specialite,
            'structure_id' => auth()->user()->structure_id,
        ]);

        return response()->json([
            'message' => 'Médecin créé avec succès',
            'medecin' => $medecin
        ], 201);
    }

    /**
     * Afficher les détails d'un médecin
     */
    public function showMedecin(User $medecin): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        // Vérifier que le médecin appartient à cet assistant et à la même structure
        if ($medecin->createur_id !== $assistantId || $medecin->structure_id !== $structureId || $medecin->role !== 'medecin') {
            return response()->json(['error' => 'Accès non autorisé à ce médecin'], 403);
        }

        return response()->json(['medecin' => $medecin]);
    }

    /**
     * Mettre à jour un médecin
     */
    public function updateMedecin(Request $request, User $medecin): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        // Vérifier que le médecin appartient à cet assistant et à la même structure
        if ($medecin->createur_id !== $assistantId || $medecin->structure_id !== $structureId || $medecin->role !== 'medecin') {
            return response()->json(['error' => 'Accès non autorisé à ce médecin'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email,' . $medecin->id,
            'specialite' => 'required|string|max:100',
        ]);

        $data = [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'specialite' => $request->specialite,
        ];

        // Mettre à jour le mot de passe seulement si fourni
        if ($request->filled('password')) {
            $request->validate([
                'password' => 'required|confirmed|min:8',
            ]);
            $data['password'] = Hash::make($request->password);
        }

        $medecin->update($data);

        return response()->json([
            'message' => 'Médecin mis à jour avec succès',
            'medecin' => $medecin
        ]);
    }

    /**
     * Supprimer un médecin
     */
    public function destroyMedecin(User $medecin): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $assistantId = auth()->id();
        $structureId = auth()->user()->structure_id;

        // Vérifier que le médecin appartient à cet assistant et à la même structure
        if ($medecin->createur_id !== $assistantId || $medecin->structure_id !== $structureId || $medecin->role !== 'medecin') {
            return response()->json(['error' => 'Accès non autorisé à ce médecin'], 403);
        }

        $medecin->delete();

        return response()->json(['message' => 'Médecin supprimé avec succès']);
    }
}