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

    /**
     * Afficher la liste des patients (utilisateurs avec rôle patient) pour la création de rendez-vous
     */
    public function listPatient(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        try {
            // Récupérer les utilisateurs avec le rôle "patient"
            $patients = User::where('role', 'patient')
                           ->orderBy('nom') // ou 'nom' selon votre structure
                           ->get(['id', 'nom','prenom', 'email', 'telephone', 'age','groupe_sanguin','antecedants','allergies']);

            return response()->json(['patients' => $patients]);
            
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
                'date_consultation' => $request->date_consultation,
                'motif' => $request->motif,
                'diagnostic' => $request->diagnostic,
                'traitement' => $request->traitement,
                'notes' => $request->notes,
                'prix' => $request->prix,
                'statut' => $request->statut ?? 'planifie', // ou 'planifie' selon votre logique
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