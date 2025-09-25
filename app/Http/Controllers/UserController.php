<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;



class UserController extends Controller
{
    public function index()
    {
        $utilisateurs = auth()->user()->isSuperAdmin()
            ? User::with('createur')->latest()->paginate(10)
            : auth()->user()->utilisateursCrees()->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $utilisateurs
        ]);
    }

    public function create()
    {
        $rolesDisponibles = $this->getRolesDisponibles();

        return response()->json([
            'success' => true,
            'data' => ['rolesDisponibles' => $rolesDisponibles]
        ]);
    }

    public function store(Request $request)
    {
        $rolesDisponibles = $this->getRolesDisponibles();

        $request->validate([
            'nom' => ['required', 'string', 'max:50'],
            'prenom' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', Rule::in($rolesDisponibles)],
            'specialite' => ['nullable', 'string', 'max:100'],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'groupe_sanguin' => ['nullable', 'string', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'antecedants' => ['nullable', 'string'],
            'allergies' => ['nullable', 'string', 'max:200'],
        ]);

        $userData = [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'createur_id' => auth()->id(),
            'actif' => true,
        ];

        if ($request->role === 'medecin') {
            $userData['specialite'] = $request->specialite;
        }

        if ($request->role === 'patient') {
            $userData['age'] = $request->age;
            $userData['adresse'] = $request->adresse;
            $userData['telephone'] = $request->telephone;
            $userData['groupe_sanguin'] = $request->groupe_sanguin;
            $userData['antecedants'] = $request->antecedants;
            $userData['allergies'] = $request->allergies;
        }

        $user = User::create($userData);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès.',
            'data' => $user
        ], 201);
    }

    public function edit(User $user)
    {
        if (!$this->canAccessUser($user)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 403);
        }

        $structures = Structure::active()->get();
        $roles = [
            'admin_structure' => 'Admin Structure',
            'medecin' => 'Médecin',
            'assistant' => 'Assistant',
            'patient' => 'Patient'
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'structures' => $structures,
                'roles' => $roles
            ]
        ]);
    }

    public function update(Request $request, User $user)
    {
        if (!$this->canAccessUser($user)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 403);
        }

        $rules = [
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ];

        if (auth()->user()->isSuperAdmin()) {
            $rules['role'] = 'required|in:admin_structure,medecin,assistant,patient';
            $rules['structure_id'] = 'nullable|exists:structures,id';
        }

        if ($request->filled('password')) {
            $rules['password'] = 'required|min:8|confirmed';
        }

        $request->validate($rules);

        $data = [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
        ];

        if (auth()->user()->isSuperAdmin()) {
            $data['role'] = $request->role;
            $data['structure_id'] = $request->structure_id;
        }

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur modifié avec succès.',
            'data' => $user
        ]);
    }

    public function toggle(User $user)
    {
        if (!$this->canAccessUser($user)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 403);
        }

        $user->update(['actif' => !$user->actif]);

        return response()->json([
            'success' => true,
            'message' => $user->actif ? 'Utilisateur activé' : 'Utilisateur désactivé',
            'data' => $user
        ]);
    }

    public function destroy(User $user)
    {
        if (!$this->canAccessUser($user)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 403);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès.'
        ]);
    }

    private function getRolesDisponibles(): array
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return ['super_admin', 'admin_structure', 'medecin', 'infirmier', 'assistant', 'patient'];
        }

        if ($user->isAdminStructure()) {
            return ['medecin', 'infirmier', 'assistant'];
        }

        if ($user->isAssistant()) {
            return ['medecin',  'patient'];
        }

        if ($user->isMedecin()) {
            return ['patient'];
        }

        return [];
    }

    private function canAccessUser(User $user): bool
    {
        $authUser = auth()->user();

        if ($authUser->role === 'super_admin') {
            return true;
        }

        if (in_array($authUser->role, ['admin_structure', 'assistant'])) {
            return $user->structure_id === $authUser->structure_id;
        }

        return $user->id === $authUser->id;
    }


    /**
 * Réinitialiser le mot de passe
 */
public function resetPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'token' => 'required|string',
        'password' => 'required|min:8|confirmed'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $validator->errors()
        ], 422);
    }

    // Vérifier le token
    $resetData = DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->first();

    if (!$resetData || !Hash::check($request->token, $resetData->token)) {
        return response()->json([
            'success' => false,
            'message' => 'Token invalide'
        ], 400);
    }

    // Vérifier l'expiration
    if (Carbon::parse($resetData->created_at)->addHours(24)->isPast()) {
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return response()->json([
            'success' => false,
            'message' => 'Token expiré'
        ], 400);
    }

    // Mettre à jour le mot de passe avec vérification de l'utilisateur
    $user = User::where('email', $request->email)->first();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ], 404);
    }

    $user->update([
        'password' => Hash::make($request->password)
    ]);

    // Supprimer le token utilisé
    DB::table('password_reset_tokens')->where('email', $request->email)->delete();

    return response()->json([
        'success' => true,
        'message' => 'Mot de passe réinitialisé avec succès'
    ]);
}

/**
 * Changer le mot de passe (utilisateur connecté)
 */
public function changePassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'current_password' => 'required',
        'password' => 'required|min:8|confirmed'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $validator->errors()
        ], 422);
    }

    // Récupérer l'utilisateur connecté avec une vérification de null
    $user = $request->user();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur non authentifié'
        ], 401);
    }

    // Vérifier que l'utilisateur existe bien en base de données
    $user = User::find($user->id);
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ], 404);
    }

    // Vérifier l'ancien mot de passe
    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Mot de passe actuel incorrect'
        ], 400);
    }

    // Mettre à jour le mot de passe
    $user->update([
        'password' => Hash::make($request->password)
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Mot de passe changé avec succès'
    ]);
}




}