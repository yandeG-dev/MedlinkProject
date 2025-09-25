<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Prescription;
use App\Models\Consultation;
use App\Models\Rdv;
use App\Models\Structure;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class AdminStructureController extends Controller
{
    // =============================================
    // DASHBOARD ET STATISTIQUES
    // =============================================

    /**
     * Tableau de bord complet avec toutes les données de la structure
     */
    public function dashboardComplet(): JsonResponse
    {
        try {
            // Vérification d'authentification et de rôle
            if (!auth()->check()) {
                return response()->json(['error' => 'Non authentifié'], 401);
            }

            if (!auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;

            // Vérifier que l'admin a une structure
            if (!$structureId) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'stats' => [
                            'total_utilisateurs' => 0,
                            'assistants_total' => 0,
                            'medecins_total' => 0,
                            'patients_total' => 0,
                            'utilisateurs_actifs' => 0,
                            'utilisateurs_inactifs' => 0,
                            'prescriptions_total' => 0,
                            'consultations_total' => 0,
                            'rdv_total' => 0,
                            'rdv_planifies' => 0,
                            'rdv_termines' => 0,
                            'rdv_annules' => 0,
                            'prescriptions_mois' => 0,
                            'consultations_mois' => 0,
                            'rdv_mois' => 0,
                        ],
                        'prescriptions_recentes' => [],
                        'consultations_recentes' => [],
                        'rdv_prochains' => [],
                        'structure' => null
                    ],
                    'message' => 'Aucune structure associée'
                ]);
            }

            // Statistiques complètes de la structure
            $stats = [
                // Utilisateurs
                'total_utilisateurs' => User::where('structure_id', $structureId)->count(),
                'assistants_total' => User::where('role', 'assistant')->where('structure_id', $structureId)->count(),
                'medecins_total' => User::where('role', 'medecin')->where('structure_id', $structureId)->count(),
                'patients_total' => User::where('role', 'patient')->where('structure_id', $structureId)->count(),
                'utilisateurs_actifs' => User::where('structure_id', $structureId)->where('actif', true)->count(),
                'utilisateurs_inactifs' => User::where('structure_id', $structureId)->where('actif', false)->count(),

                // Activités - Utilisation sécurisée de whereHas
                'prescriptions_total' => Prescription::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->count(),

                'consultations_total' => Consultation::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->count(),

                'rdv_total' => Rdv::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->count(),

                'rdv_planifies' => Rdv::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->where('statut', 'planifié')->count(),

                'rdv_termines' => Rdv::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->where('statut', 'terminé')->count(),

                'rdv_annules' => Rdv::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->where('statut', 'annulé')->count(),

                // Ce mois
                'prescriptions_mois' => Prescription::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->whereMonth('created_at', now()->month)->count(),

                'consultations_mois' => Consultation::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->whereMonth('created_at', now()->month)->count(),

                'rdv_mois' => Rdv::whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })->whereMonth('created_at', now()->month)->count(),
            ];

            // Dernières activités
            $prescriptions_recentes = Prescription::with(['medecin', 'patient'])
                ->whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            $consultations_recentes = Consultation::with(['medecin', 'patient'])
                ->whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })
                ->orderBy('date_consultation', 'desc')
                ->take(5)
                ->get();

            $rdv_prochains = Rdv::with(['medecin', 'patient'])
                ->whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })
                ->where('statut', 'planifié')
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
                    'structure' => Structure::find($structureId)
                ],
                'message' => 'Tableau de bord complet récupéré avec succès'
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
     * Dashboard simplifié (alias pour compatibilité)
     */
    public function dashboard(): JsonResponse
    {
        return $this->dashboardComplet();
    }

    // =============================================
    // GESTION DE LA STRUCTURE
    // =============================================

    /**
     * Vérifier si l'admin a déjà une structure
     */
    public function checkStructure(): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $user = auth()->user();
                   $hasStructure = $user->structure_id !== null;

            return response()->json([
                'success' => true,
                'data' => ['has_structure' => $hasStructure]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la vérification de la structure'
            ], 500);
        }
    }

    /**
     * Créer une nouvelle structure
     */
    public function storeStructure(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $request->validate([
                'nom' => 'required|string|max:100',
                'adresse' => 'required|string|max:255',
                'telephone' => 'required|string|max:20',
                'email' => 'required|email|unique:structures,email',
                'type' => 'required|string|max:100',
            ]);

            $structure = Structure::create([
                'nom' => $request->nom,
                'adresse' => $request->adresse,
                'telephone' => $request->telephone,
                'email' => $request->email,
                'type' => $request->type,
                'actif' => true,
            ]);

            // Mettre à jour l'utilisateur avec la structure_id
            auth()->user()->update(['structure_id' => $structure->id]);

            return response()->json([
                'success' => true,
                'message' => 'Structure créée avec succès',
                'data' => $structure
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la création de la structure',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher les détails de la structure de l'admin
     */
    public function showStructure(): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

              $structure = Structure::find($user->structure_id);

            if (!$structure) {
                return response()->json([
                    'success' => false,
                    'error' => 'Structure non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $structure
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération de la structure'
            ], 500);
        }
    }

    /**
     * Mettre à jour la structure
     */
    public function updateStructure(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

           $structure = Structure::find(auth()->user()->structure_id);

            if (!$structure) {
                return response()->json([
                    'success' => false,
                    'error' => 'Structure non trouvée'
                ], 404);
            }

            $request->validate([
                'nom' => 'required|string|max:100',
                'adresse' => 'required|string|max:255',
                'telephone' => 'required|string|max:20',
                'email' => 'required|email|unique:structures,email,' . $structure->id,
                'type' => 'required|string|max:100',
            ]);

            $structure->update([
                'nom' => $request->nom,
                'adresse' => $request->adresse,
                'telephone' => $request->telephone,
                'email' => $request->email,
                'type' => $request->type,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Structure mise à jour avec succès',
                'data' => $structure
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour de la structure',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // =============================================
    // GESTION DES UTILISATEURS
    // =============================================

    /**
     * Lister tous les utilisateurs de la structure avec pagination
     */
    public function listUtilisateursStructure(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;
            
            if (!$structureId) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $query = User::where('structure_id', $structureId);

            // Filtre par rôle
            if ($request->has('role') && $request->role) {
                $query->where('role', $request->role);
            }

            // Filtre par statut
            if ($request->has('actif') && $request->actif !== '') {
                $query->where('actif', $request->actif);
            }

            $utilisateurs = $query->orderBy('role')
                                 ->orderBy('nom')
                                 ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $utilisateurs
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des utilisateurs'
            ], 500);
        }
    }

    /**
     * Ajouter un nouvel utilisateur (tout rôle)
     */
    public function storeUtilisateur(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;
            
            if (!$structureId) {
                return response()->json(['error' => 'Aucune structure associée'], 400);
            }

            $request->validate([
                'nom' => 'required|string|max:50',
                'prenom' => 'required|string|max:50',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|confirmed|min:8',
                'role' => 'required|in:assistant,medecin,patient',
                'telephone' => 'nullable|string|max:20',
                'specialite' => 'required_if:role,medecin|string|max:100',
                'age' => 'required_if:role,patient|integer|min:0',
                'adresse' => 'nullable|string|max:255',
            ]);

            $userData = [
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'actif' => true,
                'createur_id' => auth()->id(),
                'structure_id' => $structureId,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
            ];

            // Champs spécifiques par rôle
            if ($request->role === 'medecin') {
                $userData['specialite'] = $request->specialite;
            }

            if ($request->role === 'patient') {
                $userData['age'] = $request->age;
            }

            $utilisateur = User::create($userData);

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->role) . ' créé avec succès',
                'data' => $utilisateur
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la création de l\'utilisateur',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Voir les détails complets d'un utilisateur
     */
    public function showUtilisateur(User $utilisateur): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Vérifier que l'utilisateur appartient à la structure
            if ($utilisateur->structure_id !== auth()->user()->structure_id) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Charger les données selon le rôle
            $donneesSupplementaires = [];

            if ($utilisateur->role === 'medecin') {
                $donneesSupplementaires['prescriptions'] = Prescription::whereHas('medecin', function($query) use ($utilisateur) {
                    $query->where('structure_id', $utilisateur->structure_id);
                })->count();
                    
                $donneesSupplementaires['consultations'] = Consultation::whereHas('medecin', function($query) use ($utilisateur) {
                    $query->where('structure_id', $utilisateur->structure_id);
                })->count();
            }

            if ($utilisateur->role === 'patient') {
                $donneesSupplementaires['prescriptions'] = Prescription::whereHas('patient', function($query) use ($utilisateur) {
                    $query->where('structure_id', $utilisateur->structure_id);
                })->count();
                    
                $donneesSupplementaires['consultations'] = Consultation::whereHas('patient', function($query) use ($utilisateur) {
                    $query->where('structure_id', $utilisateur->structure_id);
                })->count();
                    
                $donneesSupplementaires['rendez_vous'] = Rdv::whereHas('patient', function($query) use ($utilisateur) {
                    $query->where('structure_id', $utilisateur->structure_id);
                })->count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'utilisateur' => $utilisateur,
                    'statistiques' => $donneesSupplementaires
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération de l\'utilisateur'
            ], 500);
        }
    }

    /**
     * Activer/désactiver un utilisateur
     */
    public function toggleUtilisateur(User $utilisateur): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Vérifier que l'utilisateur appartient à la structure
            if ($utilisateur->structure_id !== auth()->user()->structure_id) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Vérification supplémentaire pour les assistants et médecins créés par cet admin
            if (in_array($utilisateur->role, ['assistant', 'medecin']) && $utilisateur->createur_id !== auth()->id()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $utilisateur->update(['actif' => !$utilisateur->actif]);

            $statut = $utilisateur->actif ? 'activé' : 'désactivé';
            $message = ucfirst($utilisateur->role) . " {$statut} avec succès";

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $utilisateur
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la modification de l\'utilisateur'
            ], 500);
        }
    }

    // =============================================
    // GESTION DES PATIENTS
    // =============================================

    /**
     * Lister les patients de la structure
     */
    public function listPatients(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;

            if (!$structureId) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $patients = User::where('role', 'patient')
                           ->where('structure_id', $structureId)
                           ->orderBy('nom')
                           ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $patients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des patients'
            ], 500);
        }
    }

    /**
     * Récupérer les assistants pour l'assignation des patients
     */
    public function getAssistantsForAssignment(): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $assistants = User::where('role', 'assistant')
                             ->where('createur_id', auth()->id())
                             ->where('actif', true)
                             ->orderBy('nom')
                             ->get(['id', 'nom', 'prenom', 'email']);

            return response()->json([
                'success' => true,
                'data' => $assistants
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des assistants'
            ], 500);
        }
    }

    /**
     * Créer un nouveau patient
     */
    public function storePatient(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;
            
            if (!$structureId) {
                return response()->json(['error' => 'Aucune structure associée'], 400);
            }

            $request->validate([
                'nom' => 'required|string|max:50',
                'prenom' => 'required|string|max:50',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|confirmed|min:8',
                'age' => 'required|integer|min:0|max:150',
                'adresse' => 'required|string|max:255',
                'telephone' => 'required|string|max:20',
                'assistant_id' => 'required|exists:users,id',
                'antecedants' => 'nullable|string',
                'allergies' => 'nullable|string|max:200',
                'groupe_sanguin' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            ]);

            // Vérifier que l'assistant appartient bien à cet admin
            $assistant = User::find($request->assistant_id);
            if (!$assistant || $assistant->createur_id !== auth()->id() || $assistant->role !== 'assistant') {
                return response()->json(['error' => 'Assistant invalide'], 400);
            }

            $userData = [
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'patient',
                'age' => $request->age,
                'adresse' => $request->adresse,
                'telephone' => $request->telephone,
                'actif' => true,
                'createur_id' => $request->assistant_id,
                'structure_id' => $structureId,
                'antecedants' => $request->antecedants ?? '',
                'allergies' => $request->allergies ?? '',
                'groupe_sanguin' => $request->groupe_sanguin ?? '',
            ];

            $patient = User::create($userData);

            return response()->json([
                'success' => true,
                'message' => 'Patient créé avec succès et assigné à l\'assistant',
                'data' => $patient
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la création du patient',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // =============================================
    // GESTION DES ACTIVITÉS (PRESCRIPTIONS, CONSULTATIONS, RDV)
    // =============================================

    /**
     * Lister toutes les prescriptions de la structure
     */
    public function listPrescriptionsStructure(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;

            if (!$structureId) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $prescriptions = Prescription::with(['medecin', 'patient'])
                ->whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $prescriptions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des prescriptions'
            ], 500);
        }
    }

    /**
     * Lister toutes les consultations de la structure
     */
    public function listConsultationsStructure(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;

            if (!$structureId) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $consultations = Consultation::with(['medecin', 'patient'])
                ->whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })
                ->orderBy('date_consultation', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $consultations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des consultations'
            ], 500);
        }
    }

    /**
     * Lister tous les rendez-vous de la structure
     */
    public function listRendezVousStructure(Request $request): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;

            if (!$structureId) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $rendezVous = Rdv::with(['medecin', 'patient'])
                ->whereHas('medecin', function($query) use ($structureId) {
                    $query->where('structure_id', $structureId);
                })
                ->orderBy('date_rdv', 'desc')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $rendezVous
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des rendez-vous'
            ], 500);
        }
    }

    // =============================================
    // STATISTIQUES DÉTAILLÉES
    // =============================================

    /**
     * Statistiques détaillées par médecin
     */
    public function statsParMedecin(): JsonResponse
    {
        try {
            if (!auth()->check() || !auth()->user()->isAdminStructure()) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $structureId = auth()->user()->structure_id;

            if (!$structureId) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $medecins = User::where('role', 'medecin')
                           ->where('structure_id', $structureId)
                           ->where('actif', true)
                           ->get();

            $statsMedecins = [];

            foreach ($medecins as $medecin) {
                $statsMedecins[] = [
                    'medecin' => $medecin->only(['id', 'nom', 'prenom', 'specialite', 'email']),
                    'prescriptions' => Prescription::whereHas('medecin', function($query) use ($medecin) {
                        $query->where('structure_id', $medecin->structure_id);
                    })->count(),
                    'consultations' => Consultation::whereHas('medecin', function($query) use ($medecin) {
                        $query->where('structure_id', $medecin->structure_id);
                    })->count(),
                    'rdv_planifies' => Rdv::whereHas('medecin', function($query) use ($medecin) {
                        $query->where('structure_id', $medecin->structure_id);
                    })->where('statut', 'planifié')
                      ->where('date_rdv', '>=', now())
                      ->count(),
                    'patients_uniques' => Consultation::whereHas('medecin', function($query) use ($medecin) {
                        $query->where('structure_id', $medecin->structure_id);
                    })->distinct('patient_id')
                      ->count('patient_id')
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $statsMedecins
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }
}