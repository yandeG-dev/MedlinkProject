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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        // CORRECTION : Pour les médecins, ne pas filtrer par createur_id
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
            
            // CORRECTION : Médecins de la structure sans filtre createur_id
            'total_medecins' => User::where('role', 'medecin')
                                   ->where('structure_id', $structureId) // Uniquement par structure
                                   ->count(),
            
            // CORRECTION : Médecins ajoutés ce mois dans la structure
            'medecins_ajoutes_ce_mois' => User::where('role', 'medecin')
                                             ->where('structure_id', $structureId) // Uniquement par structure
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
                'stats' => $stats, // Maintenant avec 7 propriétés
                'prescriptions_recentes' => $prescriptions_recentes,
                'consultations_recentes' => $consultations_recentes,
                'rdv_prochains' => $rdv_prochains,
                'patients_recents' => User::where('role', 'patient')
                                         ->where('createur_id', $assistantId)
                                         ->where('structure_id', $structureId)
                                         ->orderBy('created_at', 'desc')
                                         ->take(5)
                                         ->get(['id', 'nom', 'prenom', 'email', 'created_at']),
                // CORRECTION : Médecins récents de la structure sans filtre createur_id
                'medecins_recents' => User::where('role', 'medecin')
                                         ->where('structure_id', $structureId) // Uniquement par structure
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
    // Vérification d'authentification et de rôle
    if (!auth()->check() || !auth()->user()->isAssistant()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    try {
        $structureId = auth()->user()->structure_id;
        
        // Validation de la structure_id
        if (!$structureId) {
            return response()->json([
                'success' => false,
                'error' => 'Structure non associée à cet utilisateur'
            ], 400);
        }

        $consultations = Consultation::with(['medecin', 'patient'])
            ->where('structure_id', $structureId)
            ->orderBy('date_consultation', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $consultations
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur lors de la récupération des consultations: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'error' => 'Erreur serveur lors de la récupération des consultations'
        ], 500);
    }
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

   public function listRendezVousStructure(Request $request)
{
    if (!auth()->check() || !auth()->user()->isAssistant()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    $structureId = auth()->user()->structure_id;
    $perPage = $request->get('per_page', 15);
    
    $rendezVous = Rdv::with('patient')
        ->where('structure_id', $structureId)
        ->orderBy('date_rdv', 'desc')
        ->paginate($perPage);

    return response()->json([
        'data' => $rendezVous->items(),
        'pagination' => [
            'current_page' => $rendezVous->currentPage(),
            'last_page' => $rendezVous->lastPage(),
            'per_page' => $rendezVous->perPage(),
            'total' => $rendezVous->total(),
            'from' => $rendezVous->firstItem(),
            'to' => $rendezVous->lastItem(),
        ]
    ]);
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
 * Activer/Désactiver un patient
 */
public function togglePatientStatus(User $patient): JsonResponse
{
    // 🔐 Vérification d'authentification et rôle
    if (!auth()->check() || !auth()->user()->isAssistant()) {
        Log::warning("⛔ Accès refusé : utilisateur non authentifié ou non assistant");
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    $assistantId = auth()->id();
    $structureId = auth()->user()->structure_id;

    Log::info("🔁 Requête de changement de statut pour patient {$patient->id} par assistant {$assistantId}");

    // 🔍 Vérification d'appartenance
    if (
        $patient->createur_id !== $assistantId ||
        $patient->structure_id !== $structureId ||
        $patient->role !== 'patient'
    ) {
        Log::warning("⛔ Accès refusé au patient {$patient->id} — créateur: {$patient->createur_id}, structure: {$patient->structure_id}, rôle: {$patient->role}");
        return response()->json(['error' => 'Accès non autorisé à ce patient'], 403);
    }

    // 🔄 Inversion du statut
    try {
        $ancienStatut = $patient->actif;
        $patient->update(['actif' => !$ancienStatut]);
        $patient->refresh();

        $nouveauStatut = $patient->actif ? 'activé' : 'désactivé';

        Log::info("✅ Patient {$patient->id} statut changé : {$ancienStatut} → {$patient->actif}");

        return response()->json([
            'message' => "Patient {$nouveauStatut} avec succès",
            'patient' => $patient
        ]);
    } catch (\Exception $e) {
        Log::error("❌ Erreur lors du changement de statut du patient {$patient->id} : " . $e->getMessage());
        return response()->json(['error' => 'Erreur serveur'], 500);
    }
}

  /**
     * Afficher les détails complets d'un patient
     */
    public function showPatientDetails(User $patient): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAssistant()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $assistantId = auth()->id();
            $structureId = auth()->user()->structure_id;

            // Vérifier que le patient appartient à cet assistant et à la même structure
            if ($patient->createur_id !== $assistantId || $patient->structure_id !== $structureId || $patient->role !== 'patient') {
                return response()->json(['error' => 'Accès non autorisé à ce patient'], 403);
            }

            // CORRECTION : Charger les rendez-vous avec une relation correcte
            // Supposons que la relation s'appelle 'rendezvous' (au singulier)
            $patient->load(['rendezvous' => function($query) {
                $query->orderBy('date_rdv', 'desc')
                      ->limit(5);
            }]);

            // Calculer les statistiques
            $totalRendezVous = $patient->rendezvous->count();
            $prochainRdv = $patient->rendezvous
                ->where('date_rdv', '>=', now())
                ->sortBy('date_rdv')
                ->first();

            return response()->json([
                'patient' => $patient,
                'stats' => [
                    'total_rendez_vous' => $totalRendezVous,
                    'prochain_rdv' => $prochainRdv,
                    'rendez_vous_recents' => $patient->rendezvous->take(5)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur showPatientDetails: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Lister les médecins pour l'assistant
     */
   public function listMedecins(): JsonResponse
{
    if (!auth()->check() || !auth()->user()->isAssistant()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    $structureId = auth()->user()->structure_id; // ← Récupère la structure de l'utilisateur connecté

    try {
        $medecins = User::where('role', 'medecin')
            ->where('structure_id', $structureId) // ← Filtre par la structure de l'assistant
            ->where('actif', true)
            ->orderBy('nom')
            ->get(['id', 'nom', 'prenom', 'email', 'specialite', 'actif', 'created_at']);

        return response()->json([
            'success' => true,
            'medecins' => $medecins
        ]);
    } catch (\Exception $e) {
        // ...
    }
}
    /**
     * Afficher les détails d'un médecin
     */
    public function showMedecin(User $medecin): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structureId = auth()->user()->structure_id;

        // CORRECTION : Vérifier seulement que le médecin appartient à la même structure
        if ($medecin->structure_id !== $structureId || $medecin->role !== 'medecin') {
            return response()->json(['error' => 'Accès non autorisé à ce médecin'], 403);
        }

        return response()->json(['medecin' => $medecin]);
    }

 
   
    /**
     * Créer un rendez-vous
     */
 
public function storeRendezVous(Request $request): JsonResponse
{
    Log::info('=== DÉBUT Création RDV ===');
    Log::info('Utilisateur connecté:', [
        'user_id' => auth()->id(),
        'structure_id' => auth()->user()->structure_id,
        'role' => auth()->user()->role
    ]);

    if (!auth()->check() || !auth()->user()->isAssistant()) {
        Log::error('Accès non autorisé - Utilisateur non assistant ou non connecté');
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    // ✅ Injection chirurgicale de l'assistant_id
    $request->merge(['assistant_id' => auth()->id()]);

    try {
        $request->validate([
            'assistant_id' => 'required|exists:users,id',
            'patient_id' => 'required|exists:users,id',
            'medecin_id' => 'required|exists:users,id',
            'date_rdv' => 'required|date|after:now',
            'motif' => 'required|string|max:500',
            'statut' => 'required|in:planifie,termine,annule',
            'notes' => 'nullable|string'
        ]);
        Log::info('✅ Validation réussie', $request->only([
            'assistant_id', 'patient_id', 'medecin_id', 'date_rdv', 'motif', 'statut'
        ]));
    } catch (\Exception $e) {
        Log::error('❌ Échec validation', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'error' => 'Données invalides: ' . $e->getMessage()
        ], 422);
    }

    $structureId = auth()->user()->structure_id;

    try {
        // ✅ Vérification patient et médecin dans la même structure
        $patient = User::where('id', $request->patient_id)
            ->where('structure_id', $structureId)
            ->where('role', 'patient')
            ->first();

        $medecin = User::where('id', $request->medecin_id)
            ->where('structure_id', $structureId)
            ->where('role', 'medecin')
            ->first();

        if (!$patient || !$medecin) {
            Log::error('❌ Patient ou médecin introuvable dans la structure', [
                'patient_id' => $request->patient_id,
                'medecin_id' => $request->medecin_id,
                'structure_id' => $structureId
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Patient ou médecin non trouvé dans votre structure'
            ], 404);
        }

        // ✅ Création du rendez-vous
        $rendezVous = Rdv::create([
            'assistant_id' => $request->assistant_id,
            'patient_id' => $request->patient_id,
            'medecin_id' => $request->medecin_id,
            'structure_id' => $structureId,
            'date_rdv' => $request->date_rdv,
            'motif' => $request->motif,
            'notes' => $request->notes,
            'statut' => $request->statut
        ]);

        Log::info('✅ Rendez-vous créé avec succès', ['id' => $rendezVous->id]);

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous créé avec succès',
            'data' => $rendezVous
        ], 201);

    } catch (\Exception $e) {
        Log::error('❌ Erreur lors de la création du rendez-vous', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Erreur interne: ' . $e->getMessage()
        ], 500);
    }
}




public function showRendezVous(Rdv $rendezVous): JsonResponse
{
    $assistant = Auth::user();
$rdvTous = Rdv::with(['patient', 'medecin'])
    ->where('structure_id', $assistant->structure_id)
    ->orderBy('date_rdv', 'desc')
    ->take(5)
    ->get();
    
    // 🔒 Vérifie que le RDV appartient à la même structure
    if ($rendezVous->structure_id !== $assistant->structure_id) {
        return response()->json([
            'success' => false,
            'error' => 'Accès non autorisé à ce rendez-vous.'
        ], 403);
    }

    // 🔄 Charge les relations utiles
    $rendezVous->load(['patient', 'medecin']);

    return response()->json([
        'success' => true,
        'data' => $rendezVous
    ]);
}
 /**
 * Mettre à jour un rendez-vous (version corrigée sans heure_rdv)
 */
public function updateRendezVous(Request $request, Rdv $rendezVous): JsonResponse
{
    Log::info('=== DÉBUT UPDATE RDV ENCODAGE ===');
    Log::info('Données brutes:', $request->all());

    try {
        if (!auth()->check() || !auth()->user()->isAssistant()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $user = auth()->user();
        $structureId = $user->structure_id;

        if ($rendezVous->structure_id != $structureId) {
            return response()->json(['error' => 'Accès non autorisé à ce rendez-vous'], 403);
        }

        // ✅ CORRECTION ENCODAGE : Valider avec des caractères simples
        $validated = $request->validate([
            'date_rdv' => 'required|date',
            'statut' => 'required|string|in:en_attente,confirme,annule,termine', // ✅ Caractères simples
            'motif' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        Log::info('✅ Validation réussie:', $validated);

        // ✅ CORRECTION : Convertir les statuts si nécessaire
        $statutMapping = [
            'termine' => 'termine',
            'planifie' => 'planifie', 
            'annule' => 'annule',
            
        ];

        if (isset($statutMapping[$validated['statut']])) {
            $validated['statut'] = $statutMapping[$validated['statut']];
        }

        Log::info('📝 Données après mapping:', $validated);

        $rendezVous->update($validated);

        Log::info('✅ RDV mis à jour avec succès');

        return response()->json([
            'message' => 'Rendez-vous mis à jour avec succès',
            'rendez_vous' => $rendezVous
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('❌ Erreur validation:', $e->errors());
        return response()->json([
            'error' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('💥 Erreur updateRendezVous: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return response()->json([
            'error' => 'Erreur lors de la mise à jour du rendez-vous: ' . $e->getMessage()
        ], 500);
    }
}

}