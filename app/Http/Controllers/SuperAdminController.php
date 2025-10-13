<?php

namespace App\Http\Controllers;

use App\Models\Structure;
use App\Models\Prescription;
use App\Models\Consultation;
use App\Models\Rdv;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;


class SuperAdminController extends Controller
{
    // =============================================
    // DASHBOARD ET STATISTIQUES
    // =============================================

  
 public function dashboardComplet(): JsonResponse
{
    // CORRECTION 1: Vérifiez que l'utilisateur est authentifié
    if (!auth()->check()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    // CORRECTION 2: Utilisez la méthode exists() ou vérifiez directement le rôle
    if (!auth()->user()->isSuperAdmin()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    try {
        // Statistiques globales de la plateforme
        $stats = [
            // Structures
            'structures_total' => Structure::count(),
            'structures_actives' => Structure::where('actif', true)->count(),
            
            // Utilisateurs par rôle
            'super_admins_total' => User::where('role', 'super_admin')->count(),
            'admins_structure_total' => User::where('role', 'admin_structure')->count(),
            'medecins_total' => User::where('role', 'medecin')->count(),
            'assistants_total' => User::where('role', 'assistant')->count(),
            'patients_total' => User::where('role', 'patient')->count(),
            'utilisateurs_actifs' => User::where('actif', true)->count(),
            'utilisateurs_inactifs' => User::where('actif', false)->count(),

            // Activités globales
            'prescriptions_total' => Prescription::count(),
            'consultations_total' => Consultation::count(),
            'rdv_total' => 0, // Temporairement à 0 en attendant de créer la table
            'rdv_planifies' => 0, // Temporairement à 0

            // Ce mois
            'utilisateurs_mois' => User::whereMonth('created_at', now()->month)->count(),
            'prescriptions_mois' => Prescription::whereMonth('created_at', now()->month)->count(),
            'consultations_mois' => Consultation::whereMonth('created_at', now()->month)->count(),
        ];

        // Structures récentes
        $structures_recentes = Structure::with('admin')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Admins récents
        $admins_recents = User::where('role', 'admin_structure')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'structures_recentes' => $structures_recentes,
                'admins_recents' => $admins_recents
            ],
            'message' => 'Tableau de bord super admin récupéré avec succès'
        ]);

    } catch (\Exception $e) {
        // CORRECTION 3: Ajoutez return ici
        return response()->json([
            'success' => false,
            'error' => 'Erreur lors de la récupération du tableau de bord'
        ], 500);
    }
}

    /**
     * Rapports d'activité de la plateforme
     */
    public function rapportsActivite(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $debut = $request->get('debut', Carbon::now()->subMonth()->format('Y-m-d'));
        $fin = $request->get('fin', Carbon::now()->format('Y-m-d'));

        $rapport = [
            'periode' => ['debut' => $debut, 'fin' => $fin],
            'nouveaux_utilisateurs' => User::whereBetween('created_at', [$debut, $fin])->count(),
            'nouvelles_structures' => Structure::whereBetween('created_at', [$debut, $fin])->count(),
            'prescriptions_crees' => Prescription::whereBetween('created_at', [$debut, $fin])->count(),
            'consultations_crees' => Consultation::whereBetween('created_at', [$debut, $fin])->count(),
            'rdv_crees' => Rdv::whereBetween('created_at', [$debut, $fin])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $rapport
        ]);
    }

    // =============================================
    // GESTION DES STRUCTURES
    // =============================================

    /**
     * Créer une structure complète avec son admin
     */
  public function storeStructures(Request $request): JsonResponse
{
    if (!auth()->check() || auth()->user()->role !== 'super_admin') {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    $request->validate([
        // Données de la structure
        'structure_nom' => 'required|string|max:100',
        'structure_adresse' => 'required|string|max:255',
        'structure_telephone' => 'required|string|max:20',
        'structure_email' => 'required|email|unique:structures,email',
        'structure_type' => 'required|string|max:100',
        
        // Données de l'admin
        'admin_nom' => 'required|string|max:50',
        'admin_prenom' => 'required|string|max:50',
        'admin_email' => 'required|email|unique:users,email',
        'admin_password' => 'required|confirmed|min:8',
    ]);

    DB::beginTransaction();

    try {
        // Créer la structure
        $structure = Structure::create([
            'nom' => $request->structure_nom,
            'adresse' => $request->structure_adresse,
            'telephone' => $request->structure_telephone,
            'email' => $request->structure_email,
            'type' => $request->structure_type,
            'actif' => true,
        ]);

        // Créer l'admin de la structure
        $admin = User::create([
            'nom' => $request->admin_nom,
            'prenom' => $request->admin_prenom,
            'email' => $request->admin_email,
            'password' => bcrypt($request->admin_password),
            'role' => 'admin_structure',
            'actif' => true,
            'createur_id' => auth()->id(),
            'structure_id' => $structure->id,
        ]);


        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Structure et administrateur créés avec succès',
            'data' => [
                'structure' => $structure,
                'admin' => $admin
            ]
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        // AFFICHEZ L'ERREUR RÉELLE
        return response()->json([
            'success' => false,
            'error' => 'Erreur lors de la création',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}


public function updateStructure(Request $request, Structure $structure): JsonResponse
{
    $request->validate([
        'nom' => 'required|string|max:255',
        'adresse' => 'nullable|string|max:255',
        'email' => 'required|email|unique:structures,email,' . $structure->id,
        'telephone' => 'nullable|string|max:20',
        'type' => 'nullable|string|max:50',
        'actif' => 'nullable|boolean',
    ]);

    $structure->update([
        'nom' => $request->nom,
        'adresse' => $request->adresse,
        'email' => $request->email,
        'telephone' => $request->telephone,
        'type' => $request->type,
        'actif' => $request->actif,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Structure mise à jour avec succès',
        'data' => $structure
    ]);
}

    /**
     * Lister toutes les structures
     */
    public function listStructures(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structures = Structure::with(['users'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $structures
        ]);
    }

    /**
     * Voir les détails d'une structure avec ses statistiques
     */
    public function showStructure(Structure $structure): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $stats = [
            'utilisateurs_total' => User::where('structure_id', $structure->id)->count(),
            'medecins_total' => User::where('structure_id', $structure->id)->where('role', 'medecin')->count(),
            'assistants_total' => User::where('structure_id', $structure->id)->where('role', 'assistant')->count(),
            'patients_total' => User::where('structure_id', $structure->id)->where('role', 'patient')->count(),
            'prescriptions_total' => Prescription::where('structure_id', $structure->id)->count(),
            'consultations_total' => Consultation::where('structure_id', $structure->id)->count(),
            'rdv_total' => Rdv::where('structure_id', $structure->id)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'structure' => $structure->load('admin'),
                'statistiques' => $stats
            ]
        ]);
    }

    /**
     * Activer/désactiver une structure
     */
    public function toggleStructure(Structure $structure): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structure->update([
            'actif' => !$structure->actif
        ]);

        $statut = $structure->actif ? 'activée' : 'désactivée';
        $message = "Structure {$statut} avec succès";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $structure
        ]);
    }

    /**
     * Statistiques détaillées par structure
     */
    public function statsParStructure(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structures = Structure::withCount(['users as total_utilisateurs'])
            ->withCount(['users as medecins_count' => function($query) {
                $query->where('role', 'medecin');
            }])
            ->withCount(['users as assistants_count' => function($query) {
                $query->where('role', 'assistant');
            }])
            ->withCount(['users as patients_count' => function($query) {
                $query->where('role', 'patient');
            }])
            ->get();

        $statsStructures = [];

        foreach ($structures as $structure) {
            $statsStructures[] = [
                'structure' => $structure->only(['id', 'nom', 'type', 'email', 'actif']),
                'utilisateurs' => $structure->total_utilisateurs,
                'medecins' => $structure->medecins_count,
                'assistants' => $structure->assistants_count,
                'patients' => $structure->patients_count,
                'prescriptions' => Prescription::where('structure_id', $structure->id)->count(),
                'consultations' => Consultation::where('structure_id', $structure->id)->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $statsStructures
        ]);
    }

    // =============================================
    // GESTION DES UTILISATEURS (FONCTIONS FUSIONNÉES)
    // =============================================

    /**
     * Créer un utilisateur de n'importe quel rôle (fusion de storeAdmin et storeUtilisateurGlobal)
     */
    public function storeUtilisateurGlobal(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
            'role' => 'required|in:super_admin,admin_structure,medecin,assistant,patient,infirmier',
            'structure_id' => 'nullable|exists:structures,id',
            'telephone' => 'nullable|string|max:20',
            'specialite' => 'required_if:role,medecin,infirmier|string|max:100',
            'age' => 'required_if:role,patient|integer|min:0',
            'actif' => 'sometimes|boolean',
            'allergies' => 'nullable|string|max:255',
            'antecedants' => 'nullable|string|max:255',
            'groupe_sanguin' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
        ]);

        $userData = [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
            'actif' => $request->get('actif', true), // Par défaut actif
            'createur_id' => auth()->id(),
            'structure_id' => $request->structure_id,
            'telephone' => $request->telephone,

        ];

        // Champs spécifiques par rôle
        if (in_array($request->role, ['medecin', 'infirmier'])) {
            $userData['specialite'] = $request->specialite;
        }

        if ($request->role === 'patient') {
            $userData['age'] = $request->age;
            $userData['allergies'] = $request->allergies ?? '';
            $userData['antecedants'] = $request->antecedants ?? '';
            $userData['groupe_sanguin'] = $request->groupe_sanguin ?? '';
        }

        // Pour les admins structure, on peut ajouter des champs supplémentaires si besoin
        if ($request->role === 'admin_structure') {
            $userData['antecedants'] = $request->get('antecedants', '');
            $userData['allergies'] = $request->get('allergies', '');
        }

        $utilisateur = User::create($userData);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $utilisateur
        ], 201);
    }

    /**
     * Lister tous les utilisateurs avec filtres (fusion de listAdmins et listUtilisateursGlobaux)
     */
    public function listUtilisateursGlobaux(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $query = User::with(['structure', 'createur']);

        // Filtre par rôle
        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        // Filtre par structure
        if ($request->has('structure_id') && $request->structure_id) {
            $query->where('structure_id', $request->structure_id);
        }

        // Filtre par statut
        if ($request->has('actif') && $request->actif !== '') {
            $query->where('actif', $request->actif);
        }

        // Filtre par créateur (pour les admins créés par le super admin actuel)
        if ($request->has('createur_id') && $request->createur_id) {
            $query->where('createur_id', $request->createur_id);
        }

        // Tri par défaut
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        
        $utilisateurs = $query->orderBy($orderBy, $orderDirection)
                             ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $utilisateurs
        ]);
    }

    /**
     * Activer/désactiver un utilisateur ou une structure (fonction universelle)
     */
    public function toggleUtilisateurGlobal($id, Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $type = $request->get('type', 'user'); // 'user' ou 'structure'

        if ($type === 'structure') {
            $structure = Structure::findOrFail($id);
            $structure->update(['actif' => !$structure->actif]);
            
            $statut = $structure->actif ? 'activée' : 'désactivée';
            $message = "Structure {$statut} avec succès";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $structure
            ]);
        } else {
            $utilisateur = User::findOrFail($id);
            
            // Vérification supplémentaire pour les admins structure
            if ($utilisateur->role === 'admin_structure' && $utilisateur->createur_id !== auth()->id()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $utilisateur->update(['actif' => !$utilisateur->actif]);

            $statut = $utilisateur->actif ? 'activé' : 'désactivé';
            $message = "Utilisateur {$statut} avec succès";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $utilisateur
            ]);
        }
    }

    /**
     * Voir les détails d'un utilisateur spécifique
     */
  public function showUtilisateur($user): JsonResponse
{
    if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    // Si le paramètre n'est pas numérique, c'est une erreur
    if (!is_numeric($user)) {
        return response()->json([
            'success' => false,
            'error' => 'ID utilisateur invalide. Un ID numérique est requis.'
        ], 400);
    }

    try {
        $userModel = User::findOrFail($user);
        
        // Vérification supplémentaire pour les admins structure
        if ($userModel->role === 'admin_structure' && $userModel->createur_id !== auth()->id()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $userModel->load(['structure', 'createur'])
        ]);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Utilisateur non trouvé avec l\'ID: ' . $user
        ], 404);
    }
}
    /**
     * Mettre à jour un utilisateur
     */
    public function updateUtilisateur(Request $request, User $user): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        // Vérification supplémentaire pour les admins structure
        if ($user->role === 'admin_structure' && $user->createur_id !== auth()->id()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:super_admin,admin_structure,medecin,assistant,patient,infirmier',
            'structure_id' => 'nullable|exists:structures,id',
            'telephone' => 'nullable|string|max:20',
            'specialite' => 'required_if:role,medecin,infirmier|string|max:100',
            'age' => 'required_if:role,patient|integer|min:0',
            'actif' => 'required|boolean',
        ]);

        $updateData = [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'role' => $request->role,
            'actif' => $request->actif,
            'structure_id' => $request->structure_id,
            'telephone' => $request->telephone,
        ];

        // Champs spécifiques par rôle
        if (in_array($request->role, ['medecin', 'infirmier'])) {
            $updateData['specialite'] = $request->specialite;
        }

        if ($request->role === 'patient') {
            $updateData['age'] = $request->age;
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $user
        ]);
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroyUtilisateur(User $user): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        if ($user->role === 'admin_structure' && $user->createur_id !== auth()->id()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        // Empêcher la suppression des super admins
        if ($user->role === 'super_admin') {
            return response()->json(['error' => 'Impossible de supprimer un super administrateur'], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès'
        ]);
    }

    /**
     * Statistiques d'un administrateur spécifique
     */
    public function adminStats(User $admin): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        if ($admin->createur_id !== auth()->id() || $admin->role !== 'admin_structure') {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $stats = [
            'assistants' => User::where('role', 'assistant')
                ->where('createur_id', $admin->id)
                ->count(),
            'medecins' => User::where('role', 'medecin')
                ->where('createur_id', $admin->id)
                ->count(),
            'assistants_actifs' => User::where('role', 'assistant')
                ->where('createur_id', $admin->id)
                ->where('actif', true)
                ->count(),
            'medecins_actifs' => User::where('role', 'medecin')
                ->where('createur_id', $admin->id)
                ->where('actif', true)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => $admin,
                'stats' => $stats
            ]
        ]);
    }
}