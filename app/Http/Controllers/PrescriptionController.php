<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PrescriptionController extends Controller
{
    /**
     * Liste des prescriptions pour le médecin
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isMedecin()) {
            $prescriptions = Prescription::with('patient')
                ->byMedecin($user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } elseif ($user->isPatient()) {
            $prescriptions = Prescription::with('medecin')
                ->forPatient($user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $prescriptions
        ]);
    }

    /**
     * Créer une nouvelle prescription
     */
 /**
 * Créer une nouvelle prescription
 */
public function store(Request $request): JsonResponse
{
    if (!$request->user()->isMedecin()) {
        return response()->json(['error' => 'Seuls les médecins peuvent créer des prescriptions'], 403);
    }

    $medecin = $request->user();

    $validator = Validator::make($request->all(), [
        'patient_id' => 'required|exists:users,id',
        'contenu' => 'required|string',
        'instructions' => 'nullable|string',
        'notes' => 'nullable|string',
        'statut' => 'required|in:active,expirée,annulée',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // Vérifier que le patient existe, est bien un patient et dans la même structure
    $patient = User::where('id', $request->patient_id)
                  ->where('role', 'patient')
                  ->where('structure_id', $medecin->structure_id) // Même structure
                  ->first();

    if (!$patient) {
        return response()->json([
            'success' => false,
            'error' => 'Patient non trouvé ou ne fait pas partie de votre structure'
        ], 404);
    }

    $prescription = Prescription::create([
        'patient_id' => $request->patient_id,
        'medecin_id' => $medecin->id,
        'structure_id' => $medecin->structure_id, // Structure du médecin
        'contenu' => $request->contenu,
        'instructions' => $request->instructions,
        'notes' => $request->notes,
        'statut' => 'active'
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Prescription créée avec succès',
        'data' => $prescription->load('patient')
    ], 201);
}

    /**
     * Afficher une prescription spécifique
     */
    public function show(Request $request, Prescription $prescription): JsonResponse
    {
        $user = $request->user();

        // Vérifier les permissions
        if (($user->isMedecin() && $prescription->medecin_id !== $user->id) ||
            ($user->isPatient() && $prescription->patient_id !== $user->id)) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $prescription->load(['patient', 'medecin'])
        ]);
    }

    /**
     * Mettre à jour une prescription
     */
    public function update(Request $request, Prescription $prescription): JsonResponse
    {
        if (!$request->user()->isMedecin() || $prescription->medecin_id !== $request->user()->id) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

      $validator = Validator::make($request->all(), [
    'patient_id' => 'required|exists:patients,id',
    'contenu' => 'required|string',
    'structure_id' => 'required|exists:structures,id',
    'statut' => 'required|in:active,expirée,annulée',
    'instructions' => 'nullable|string',
    'notes' => 'nullable|string',

    // 'medecin_id' est récupéré via $request->user()->id donc pas besoin de le valider ici
]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $prescription->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Prescription mise à jour avec succès',
            'data' => $prescription->fresh()
        ]);
    }

    /**
     * Supprimer une prescription
     */
    public function destroy(Request $request, Prescription $prescription): JsonResponse
    {
        if (!$request->user()->isMedecin() || $prescription->medecin_id !== $request->user()->id) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $prescription->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prescription supprimée avec succès'
        ]);
    }

    /**
     * Télécharger une prescription en PDF
     */
    public function download(Prescription $prescription): JsonResponse
    {
        // Implémentation de la génération PDF
        $pdfContent = $prescription->generatePdf();

        return response()->json([
            'success' => true,
            'message' => 'PDF généré avec succès',
            'data' => [
                'pdf_content' => base64_encode($pdfContent),
                'file_name' => "prescription-{$prescription->id}.pdf"
            ]
        ]);
    }

    /**
     * Obtenir les prescriptions d'un patient spécifique
     */
    public function forPatient(Request $request, User $patient): JsonResponse
    {
        if (!$request->user()->isMedecin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        if (!$patient->isPatient()) {
            return response()->json(['error' => 'Utilisateur non trouvé ou n\'est pas un patient'], 404);
        }

        $prescriptions = Prescription::with('medecin')
            ->forPatient($patient->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $prescriptions
        ]);
    }
}