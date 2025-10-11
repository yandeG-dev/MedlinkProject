<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prescription;
use App\Models\Rdv;
use App\Models\Consultation;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class PatientController extends Controller
{
    // Liste des patients
    public function index()
    {
        $patients = User::where('role', 'patient')->get();
        return response()->json($patients);
    }

    // Ajouter un patient
    public function store(Request $request)
    {
        $validated = $request->validate([
           'nom' => 'required|string',
            'prenom' => 'nullable|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'telephone' => 'nullable|string',
            'groupe_sanguin' => 'nullable|string',
            'allergies' => 'nullable|string',
            'adresse' => 'nullable|string',
            'antecedants' => 'nullable|string',
            // 'role' => 'nullable|string',
            'age' => 'nullable|int',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['role'] = 'patient'; // rôle fixé automatiqueme
        $patient = User::create($validated);

        return response()->json($patient, 201);
    }

    // Afficher un patient
    public function show($id)
    {
        $patient = User::where('role', 'patient')->findOrFail($id);
        return response()->json($patient);
    }

    // Modifier un patient
    public function update(Request $request, $id)
    {
        $patient = User::where('role', 'patient')->findOrFail($id);

        $patient->update($request->all());

        return response()->json($patient);
    }

    // Supprimer un patient
    public function destroy($id)
    {
        $patient = User::where('role', 'patient')->findOrFail($id);
        $patient->delete();

        return response()->json(['message' => 'Patient supprimé avec succès']);
    }
public function showDossier($id)
{
    $patient = User::where('role', 'patient')
        // ->with(['rendezVous', 'prescriptions'])
        ->findOrFail($id);

    return response()->json($patient);
}

     // DASHBOARD ET STATISTIQUES
    // =============================================

    /**
     * Tableau de bord du patient
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $stats = [
            'prescriptions_total' => Prescription::where('patient_id', $user->id)->count(),
            'prescriptions_actives' => Prescription::where('patient_id', $user->id)
                                                 ->where('statut', 'active')->count(),
            'prescriptions_mois' => Prescription::where('patient_id', $user->id)
                                               ->whereMonth('created_at', now()->month)
                                               ->count(),
            'rendez_vous_planifies' => Rdv::where('patient_id', $user->id)
                                                ->where('statut', 'planifie')
                                                ->where('date_rdv', '>=', now())
                                                ->count(),
            'consultations_total' => Consultation::where('patient_id', $user->id)->count(),
        ];

        $prescriptions_recentes = Prescription::with(['medecin', 'structure'])
            ->where('patient_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $rendez_vous_prochains = Rdv::with('medecin')
            ->where('patient_id', $user->id)
            ->where('statut', 'planifie')
            ->where('date_rdv', '>=', now())
            ->orderBy('date_rdv')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'prescriptions_recentes' => $prescriptions_recentes,
            'rendez_vous_prochains' => $rendez_vous_prochains
        ]);
    }

  
    // GESTION DES PRESCRIPTIONS
    // =============================================

    /**
     * Liste des prescriptions du patient connecté
     */
    public function mesPrescriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $prescriptions = Prescription::with(['medecin', 'structure'])
            ->where('patient_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $prescriptions
        ]);
    }

    /**
     * Détails d'une prescription spécifique
     */
    public function voirPrescription(Request $request, Prescription $prescription): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient() || $prescription->patient_id !== $user->id) {
            return response()->json(['error' => 'Prescription non trouvée'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $prescription->load(['medecin', 'structure'])
        ]);
    }

    /**
     * Télécharger une prescription en PDF (retourne base64 ou URL)
     */
    public function telechargerPrescription(Request $request, Prescription $prescription): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient() || $prescription->patient_id !== $user->id) {
            return response()->json(['error' => 'Prescription non trouvée'], 404);
        }

        // Générer le contenu PDF
        $pdfContent = $prescription->generatePdf();
        
        // Retourner en base64 pour l'API
        return response()->json([
            'success' => true,
            'data' => [
                'pdf_base64' => base64_encode($pdfContent),
                'file_name' => "prescription-{$prescription->id}-" . now()->format('Y-m-d') . ".pdf",
                'prescription' => $prescription->load(['medecin', 'structure'])
            ]
        ]);
    }

    /**
     * Rechercher/filtrer les prescriptions
     */
    public function rechercherPrescriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $query = Prescription::with(['medecin', 'structure'])
            ->where('patient_id', $user->id);

        // Filtre par statut
        if ($request->has('statut') && in_array($request->statut, ['active', 'expirée', 'annulée'])) {
            $query->where('statut', $request->statut);
        }

        // Filtre par date début
        if ($request->has('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }

        // Filtre par date fin
        if ($request->has('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        // Recherche par médecin
        if ($request->has('medecin')) {
            $query->whereHas('medecin', function($q) use ($request) {
                $q->where('nom', 'like', '%' . $request->medecin . '%')
                  ->orWhere('prenom', 'like', '%' . $request->medecin . '%');
            });
        }

        $prescriptions = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $prescriptions
        ]);
    }

  
    // GESTION DES RENDEZ-VOUS
    // =============================================

    /**
     * Prendre un rendez-vous avec un médecin de la structure
     */
    public function prendreRendezVous(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $request->validate([
            'medecin_id' => 'required|exists:users,id',
            'date_rdv' => 'required|date|after:now',
            'motif' => 'required|string|max:255',
            'urgence' => 'nullable|in:faible,moyenne,haute'
        ]);

        // Vérifier que le médecin est dans la même structure
        $medecin = User::where('id', $request->medecin_id)
                      ->where('role', 'medecin')
                      ->where('structure_id', $user->structure_id)
                      ->first();

        if (!$medecin) {
            return response()->json([
                'success' => false,
                'error' => 'Médecin non trouvé ou ne fait pas partie de votre structure'
            ], 404);
        }

        $rendezVous = Rdv::create([
            'patient_id' => $user->id,
            'medecin_id' => $request->medecin_id,
            'structure_id' => $user->structure_id,
            'date_rdv' => $request->date_rdv,
            'motif' => $request->motif,
            'urgence' => $request->urgence ?? 'faible',
            'statut' => 'planifie'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous pris avec succès',
            'data' => $rendezVous->load('medecin')
        ], 201);
    }

    /**
     * Liste des rendez-vous du patient
     */
    public function mesRendezVous(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $rendezVous = Rdv::with('medecin')
            ->where('patient_id', $user->id)
            ->orderBy('date_rdv', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $rendezVous
        ]);
    }

    /**
     * Annuler un rendez-vous
     */
    public function annulerRendezVous(Request $request, Rdv $rendezVous): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient() || $rendezVous->patient_id !== $user->id) {
            return response()->json(['error' => 'Rendez-vous non trouvé'], 404);
        }

        if ($rendezVous->statut === 'annule') {
            return response()->json(['error' => 'Ce rendez-vous est déjà annulé'], 400);
        }

        $rendezVous->update(['statut' => 'annule']);

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous annulé avec succès'
        ]);
    }

    /**
     * Voir les détails d'un rendez-vous
     */
    public function voirRendezVous(Request $request, Rdv $rendezVous): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient() || $rendezVous->patient_id !== $user->id) {
            return response()->json(['error' => 'Rendez-vous non trouvé'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rendezVous->load('medecin')
        ]);
    }


    // GESTION DES CONSULTATIONS
    // =============================================

    /**
     * Liste des consultations du patient
     */
    public function mesConsultations(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $consultations = Consultation::with('medecin')
            ->where('patient_id', $user->id)
            ->orderBy('date_consultation', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $consultations
        ]);
    }

    /**
     * Voir les détails d'une consultation
     */
    public function voirConsultation(Request $request, Consultation $consultation): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient() || $consultation->patient_id !== $user->id) {
            return response()->json(['error' => 'Consultation non trouvée'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $consultation->load('medecin')
        ]);
    }


    // INFORMATIONS PERSONNELLES ET MÉDICALES
  

    /**
     * Obtenir les informations médicales du patient
     */
    public function mesInformationsMedicales(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $informations = [
            'groupe_sanguin' => $user->groupe_sanguin,
            'antecedants' => $user->antecedants,
            'allergies' => $user->allergies,
            'age' => $user->age,
            'telephone' => $user->telephone,
            'adresse' => $user->adresse,
        ];

        return response()->json([
            'success' => true,
            'data' => $informations
        ]);
    }

    /**
     * Mettre à jour les informations médicales
     */
    public function mettreAJourInformationsMedicales(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $request->validate([
            'groupe_sanguin' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'antecedants' => 'nullable|string',
            'allergies' => 'nullable|string|max:200',
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:255',
        ]);

        $user->update($request->only([
            'groupe_sanguin', 'antecedants', 'allergies', 'telephone', 'adresse'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Informations médicales mises à jour avec succès',
            'data' => $user->only(['groupe_sanguin', 'antecedants', 'allergies', 'telephone', 'adresse'])
        ]);
    }

   
    // RESSOURCES UTILES
    // =============================================

    /**
     * Liste des médecins de la structure du patient
     */
    public function medecinsDeMaStructure(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->isPatient()) {
            return response()->json(['error' => 'Accès réservé aux patients'], 403);
        }

        $medecins = User::where('role', 'medecin')
                       ->where('structure_id', $user->structure_id)
                       ->where('actif', true)
                       ->select('id', 'nom', 'prenom', 'specialite', 'email', 'telephone')
                       ->orderBy('nom')
                       ->get();

        return response()->json([
            'success' => true,
            'data' => $medecins
        ]);
    }
}



