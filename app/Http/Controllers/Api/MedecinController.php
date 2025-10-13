<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\Rdv;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;

class MedecinController extends Controller
{
    /**
     * Afficher le tableau de bord du médecin avec gestion d'erreurs
     */
    public function dashboard(): JsonResponse
    {
        try {
            // Vérification d'authentification et de rôle
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            if (!auth()->user()->isMedecin()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Accès réservé aux médecins'
                ], 403);
            }

            $medecinId = auth()->id();
            $today = Carbon::today();
            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();

            // Statistiques principales avec gestion des tables manquantes
            $stats = $this->getDashboardStats($medecinId, $today, $startOfWeek, $endOfWeek);

            // Rendez-vous à venir
            $rdvProchains = $this->getProchainsRendezVous($medecinId);

            // Consultations récentes (5 dernières)
            $consultationsRecentes = $this->getConsultationsRecentes($medecinId);

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'rdv_prochains' => $rdvProchains,
                    'consultations_recentes' => $consultationsRecentes,
                    'medecin' => [
                        'id' => auth()->user()->id,
                        'name' => auth()->user()->name,
                        'email' => auth()->user()->email
                    ]
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
     * Récupérer les données pour les graphiques (optionnel)
     */
    public function dashboardCharts(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            $medecinId = auth()->id();
            $sixMonthsAgo = Carbon::now()->subMonths(6);

            // Consultations des 6 derniers mois pour le graphique
            $consultationsParMois = Consultation::where('medecin_id', $medecinId)
                ->where('date_consultation', '>=', $sixMonthsAgo)
                ->select(
                    DB::raw('YEAR(date_consultation) as annee'),
                    DB::raw('MONTH(date_consultation) as mois'),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('annee', 'mois')
                ->orderBy('annee')
                ->orderBy('mois')
                ->get();

            return response()->json([
                'success' => true,
                'consultations_par_mois' => $consultationsParMois
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des graphiques'
            ], 500);
        }
    }

   public function listPatient(): JsonResponse
{
    if (!auth()->check() || !auth()->user()->isMedecin()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    try {
        $medecin = auth()->user();
        $structureId = $medecin->structure_id;

        // 1. Récupérer tous les assistants de la même structure
        $assistantIds = User::where('role', 'assistant')
                            ->where('structure_id', $structureId)
                            ->pluck('id');

        // 2. Récupérer les patients :
        // - créés par ces assistants
        // - OU ayant eux-mêmes structure_id = $structureId
        $patients = User::where('role', 'patient')
                        ->where(function ($query) use ($assistantIds, $structureId) {
                            $query->whereIn('createur_id', $assistantIds)
                                  ->orWhere('structure_id', $structureId);
                        })
                        ->orderBy('nom')
                        ->orderBy('prenom')
                        ->get([
                            'id', 'nom', 'prenom', 'email', 'telephone',
                            'age', 'groupe_sanguin', 'antecedants', 'allergies', 'createur_id'
                        ]);

        return response()->json([
            'success' => true,
            'data' => $patients
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Erreur lors de la récupération des patients',
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Lister les consultations du médecin
     */
    public function listConsultations(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            $consultations = Consultation::with('patient')
                ->where('medecin_id', auth()->id())
                ->where('structure_id', auth()->user()->structure_id)
                ->orderBy('date_consultation', 'desc')
                ->paginate(10);

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
     * Afficher une consultation spécifique
     */
    public function showConsultation(Consultation $consultation): JsonResponse
    {
        // Vérifier que le médecin peut voir cette consultation
        if ($consultation->medecin_id !== auth()->id()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $consultation->load('patient')
        ]);
    }

    /**
     * Stocker une nouvelle consultation
     */
    public function storeConsultation(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            $request->validate([
                'patient_id' => 'required|exists:users,id',
                'date_consultation' => 'required|date',
                'motif' => 'required|string|max:500',
                'diagnostic' => 'required|string',
                'traitement' => 'nullable|string',
                'notes' => 'nullable|string',
                'prix' => 'nullable|numeric|min:0',
            ]);

            $consultation = Consultation::create([
                'medecin_id' => auth()->id(),
                'patient_id' => $request->patient_id,
                'structure_id' => auth()->user()->structure_id,
                'date_consultation' => $request->date_consultation,
                'motif' => $request->motif,
                'diagnostic' => $request->diagnostic,
                'traitement' => $request->traitement,
                'notes' => $request->notes,
                'prix' => $request->prix,
                'statut' => $request->statut ?? '', // ou 'planifie' selon votre logique
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Consultation créée avec succès',
                'data' => $consultation->load('patient')
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la création de la consultation',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une consultation
     */
    public function updateConsultation(Request $request, Consultation $consultation): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        // Vérifier que la consultation appartient au médecin
        if ($consultation->medecin_id !== auth()->id()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            $request->validate([
                'date_consultation' => 'required|date',
                'motif' => 'required|string|max:500',
                'diagnostic' => 'required|string',
                'traitement' => 'nullable|string',
                'notes' => 'nullable|string',
                'prix' => 'nullable|numeric|min:0',
                'statut' => 'required|in:planifie,termine,annule'
            ]);

            $consultation->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Consultation mise à jour avec succès',
                'data' => $consultation->load('patient')
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Annuler un rendez-vous (médecin)
     */
    public function annulerRendezVous(Request $request, Rdv $rendezVous): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        // Vérifier que le RDV appartient au médecin
        if ($rendezVous->medecin_id !== auth()->id()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            $rendezVous->update([
                'statut' => 'annule',
                'raison_annulation' => $request->raison_annulation ?? 'Annulé par le médecin'
            ]);

            // TODO: Envoyer email au patient
            // $this->envoyerEmailAnnulation($rendezVous);

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous annulé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'annulation'
            ], 500);
        }
    }

//Creation prescription

  /**
 * Créer une nouvelle prescription
 */
public function storePrescriptions(Request $request): JsonResponse
{
    // 🔐 Vérification de l'authentification et du rôle
    if (!auth()->check() || !auth()->user()->isMedecin()) {
        Log::warning('[Prescription] Accès non autorisé', ['user_id' => auth()->id()]);
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    try {
        // ✅ Validation des données
        $validated = $request->validate([
            'contenu' => 'required|string',
            'patient_id' => 'required|exists:users,id',
            'statut' => 'in:active,expirée,annulée'
        ]);

         $prescriptions = Prescription::with([
            'patient:id,nom,prenom,email',
            'medecin:id,nom,prenom,email,specialite', // ← IMPORTANT
            'structure:id,nom'
         ]);

        // 🧠 Création de la prescription
        $prescription = Prescription::create([
            'contenu' => $validated['contenu'],
            'patient_id' => $validated['patient_id'],
            'medecin_id' => auth()->id(),
            'structure_id' => auth()->user()->structure_id,
            'statut' => $validated['statut'] ?? 'active',
        ]);

        // 🔍 Chargement des relations pour retour enrichi
        $prescription->load(['patient', 'medecin.structure']);

      Log::info('[Debug] Données reçues pour création', [
  'contenu' => $request->contenu,
  'patient_id' => $request->patient_id,
  'medecin_id' => auth()->id(),
  'structure_id' => auth()->user()?->structure_id,
  'statut' => $request->statut
]);

        return response()->json([
    'success' => true,
    'message' => 'Prescription créée avec succès',
    'data' => [
        'id' => $prescription->id,
        'contenu' => $prescription->contenu,
        'statut' => $prescription->statut,
        'date_prescription' => $prescription->created_at->format('Y-m-d'),
        'patient' => [
            'id' => $prescription->patient->id,
            'nom' => $prescription->patient->nom,
            'prenom' => $prescription->patient->prenom,
        ],
        'medecin' => [
            'id' => $prescription->medecin->id,
            'nom' => $prescription->medecin->nom ?? $prescription->medecin->name,
            'prenom' => $prescription->medecin->prenom ?? null,
            'structure' => [
                'id' => $prescription->medecin->structure->id ?? null,
                'nom' => $prescription->medecin->structure->nom ?? 'Cabinet Médical'
            ]
        ]
    ]
], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('[Prescription] Erreur de validation', ['errors' => $e->errors()]);
        return response()->json([
            'success' => false,
            'error' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        Log::error('[Prescription] Erreur serveur', ['exception' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'error' => 'Erreur lors de la création de la prescription',
            'details' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Lister les prescriptions du médecin
     */
    public function listPrescriptions(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            $prescriptions = Prescription::with('patient')
                ->where('medecin_id', auth()->id())
                ->where('structure_id', auth()->user()->structure_id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

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


      public function getPrescription($id): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur est un médecin
            if (!auth()->check() || !auth()->user()->isMedecin()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Accès non autorisé'
                ], 403);
            }

            $medecin = auth()->user();

            // Récupérer la prescription avec toutes les relations
            $prescription = Prescription::with([
                'patient:id,nom,prenom,email',
                'medecin:id,nom,prenom,email',
                'structure:id,nom'
            ])
            ->where('id', $id)
            ->where('medecin_id', $medecin->id) // Sécurité : vérifier le propriétaire
            ->first();

            if (!$prescription) {
                return response()->json([
                    'success' => false,
                    'error' => 'Prescription non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $prescription
            ]);

        } catch (\Exception $e) {
            Log::error('[Prescription] Erreur chargement', [
                'id' => $id, 
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement de la prescription'
            ], 500);
        }
    }

    public function updatePrescription(Request $request, $id)
{
    try {
        $validated = $request->validate([
            'contenu' => 'required|string',
            'patient_id' => 'required|exists:users,id',
            'medicaments' => 'sometimes|string',
            'posologie' => 'sometimes|string', 
            'duree' => 'sometimes|string',
            'instructions' => 'sometimes|string',
            'statut' => 'in:active,expirée,annulée'
        ]);

        $prescription = Prescription::findOrFail($id);
        $prescription->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Prescription modifiée avec succès',
            'data' => $prescription
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Erreur lors de la modification'
        ], 500);
    }
}

    /**
     * Récupérer les statistiques du dashboard avec gestion d'erreurs
     */
    private function getDashboardStats($medecinId, $today, $startOfWeek, $endOfWeek): array
    {
        try {
            return [
                'consultations_aujourdhui' => Consultation::where('medecin_id', $medecinId)
                    ->whereDate('date_consultation', $today)
                    ->count(),

                'consultations_semaine' => Consultation::where('medecin_id', $medecinId)
                    ->whereBetween('date_consultation', [$startOfWeek, $endOfWeek])
                    ->count(),

                'consultations_mois' => Consultation::where('medecin_id', $medecinId)
                    ->whereMonth('date_consultation', $today->month)
                    ->whereYear('date_consultation', $today->year)
                    ->count(),

                'rdv_aujourdhui' => Rdv::where('medecin_id', $medecinId)
                    ->whereDate('date_rdv', $today)
                    ->where('statut', 'planifie')
                    ->count(),

                'rdv_semaine' => Rdv::where('medecin_id', $medecinId)
                    ->whereBetween('date_rdv', [$startOfWeek, $endOfWeek])
                    ->where('statut', 'planifie')
                    ->count(),

                'patients_total' => Consultation::where('medecin_id', $medecinId)
                    ->distinct('patient_id')
                    ->count('patient_id'),

                'rdv_annules_mois' => Rdv::where('medecin_id', $medecinId)
                    ->whereMonth('date_rdv', $today->month)
                    ->whereYear('date_rdv', $today->year)
                    ->where('statut', 'annule')
                    ->count(),

                'taux_remplissage_semaine' => $this->calculateTauxRemplissage($medecinId, $startOfWeek, $endOfWeek)
            ];

        } catch (\Exception $e) {
            // Retourner des valeurs par défaut en cas d'erreur (tables manquantes, etc.)
            return [
                'consultations_aujourdhui' => 0,
                'consultations_semaine' => 0,
                'consultations_mois' => 0,
                'rdv_aujourdhui' => 0,
                'rdv_semaine' => 0,
                'patients_total' => 0,
                'rdv_annules_mois' => 0,
                'taux_remplissage_semaine' => 0
            ];
        }
    }

    /**
     * Récupérer les prochains rendez-vous
     */
    private function getProchainsRendezVous($medecinId, $structureId = null)
    {
        try {
            return Rdv::with(['patient' => function($query) {
                    $query->select('id', 'nom', 'prenom', 'telephone');
                }])
                ->where('medecin_id', $medecinId)
                ->where('structure_id', $structureId)
                ->where('date_rdv', '>=', now())
                ->where('statut', 'planifie')
                ->orderBy('date_rdv')
                ->take(5)
                ->get(['id', 'patient_id', 'date_rdv', 'motif', 'statut']);

        } catch (\Exception $e) {
            return [];
        }
    }



    /**
 * Lister les rendez-vous du médecin
 */
public function listRendezVous(): JsonResponse
{
    if (!auth()->check() || !auth()->user()->isMedecin()) {
        return response()->json(['error' => 'Accès non autorisé'], 403);
    }

    try {
        $rendezVous = Rdv::with(['patient' => function($query) {
                $query->select('id', 'nom', 'prenom', 'telephone');
            }])
            ->where('medecin_id', auth()->id())
            ->where('structure_id', auth()->user()->structure_id)
            ->orderBy('date_rdv', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $rendezVous
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Erreur lors de la récupération des rendez-vous',
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Récupérer les consultations récentes
     */
    private function getConsultationsRecentes($medecinId, $structureId = null)
    {
        try {
            return Consultation::with(['patient' => function($query) {
                    $query->select('id', 'nom', 'prenom');
                }])
                ->where('medecin_id', $medecinId)
                ->where('structure_id', $structureId)
                ->orderBy('date_consultation', 'desc')
                ->take(5)
                ->get(['id', 'patient_id', 'date_consultation', 'motif', 'diagnostic']);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Calculer le taux de remplissage de la semaine
     */
    private function calculateTauxRemplissage($medecinId, $startOfWeek, $endOfWeek): float
    {
        try {
            $rdvProgrammes = Rdv::where('medecin_id', $medecinId)
                ->whereBetween('date_rdv', [$startOfWeek, $endOfWeek])
                ->where('statut', 'planifie')
                ->count();

            // Supposons une capacité de 20 RDV par semaine
            $capaciteSemaine = 20;
            
            return $capaciteSemaine > 0 ? round(($rdvProgrammes / $capaciteSemaine) * 100, 2) : 0;

        } catch (\Exception $e) {
            return 0;
        }
    }
}