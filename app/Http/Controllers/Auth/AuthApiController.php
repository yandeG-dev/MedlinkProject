<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;



class AuthApiController extends Controller
{
    /**
     * Authentifier un utilisateur via l'API
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants fournis sont incorrects.'],
                ]);
            }
            
            // Créer un token Sanctum
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnecter un utilisateur via l'API
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Vérifier si l'utilisateur est authentifié
            if (!$request->user()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Supprimer le token current
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
 * Authentifier un utilisateur via l'API
 */
public function login(Request $request): JsonResponse
{
    try {
        Log::info('Tentative de connexion', ['email' => $request->email]);

        // Validation avec messages personnalisés
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'L\'email doit être une adresse valide',
            'password.required' => 'Le mot de passe est obligatoire',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation échouée', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            Log::warning('Utilisateur non trouvé', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects'
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            Log::warning('Mot de passe incorrect', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects'
            ], 401);
        }

        // Vérifier si l'utilisateur est actif (si vous avez ce champ)
        if (isset($user->actif) && !$user->actif) {
            Log::warning('Compte désactivé', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé'
            ], 403);
        }

        // Créer un token Sanctum
        $token = $user->createToken('api-token')->plainTextToken;

        Log::info('Connexion réussie', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                    'role' => $user->role,
                    'structure_id' => $user->structure_id,
                    'telephone' => $user->telephone,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);

    } catch (\Exception $e) {
        Log::error('Erreur serveur lors de la connexion', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur serveur interne'
        ], 500);
    }
}

    /**
     * Inscrire un nouvel utilisateur via l'API
     */
    // public function register(Request $request): JsonResponse
    // {
    //     try {
    //         $request->validate([
    //             'nom' => 'required|string|max:255',
    //             'prenom' => 'required|string|max:255',
    //             'email' => 'required|string|email|max:255|unique:users',
    //             'password' => 'required|string|min:8|confirmed',
    //         ]);

    //         $user = User::create([
    //             'nom' => $request->nom,
    //             'prenom' => $request->prenom,
    //             'email' => $request->email,
    //             'password' => Hash::make($request->password),
    //         ]);

    //         $token = $user->createToken('api-token')->plainTextToken;

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Inscription réussie',
    //             'data' => [
    //                 'user' => $user,
    //                 'token' => $token
    //             ]
    //         ], 201);

    //     } catch (ValidationException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur de validation',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur serveur',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // /**
    //  * Récupérer l'utilisateur connecté
    //  */
    // public function user(Request $request): JsonResponse
    // {
    //     try {
    //         if (!$request->user()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Utilisateur non authentifié'
    //             ], 401);
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'user' => $request->user()
    //             ]
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Erreur lors de la récupération du profil',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }



 public function forgotPassword(Request $request)
{
    try {
        Log::info('=== DÉBUT FORGOT PASSWORD ===');
        Log::info('Email reçu: ' . $request->email);

        // Validation simple
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email invalide',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier si l'utilisateur existe
        $user = User::where('email', $request->email)->first();
        $userExists = $user !== null;

        if (!$userExists) {
            // Pour la sécurité, ne pas révéler que l'email n'existe pas
            Log::info('Email non trouvé: ' . $request->email);
            return response()->json([
                'success' => true,
                'message' => 'Si cet email existe dans notre système, un code de réinitialisation a été envoyé.'
            ]);
        }

        // Générer un token simple à 6 chiffres
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        Log::info('Token généré pour ' . $request->email . ': ' . $token);

        // Sauvegarder le token (sans email)
        try {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );
            Log::info('Token sauvegardé en base');
        } catch (\Exception $dbError) {
            Log::error('Erreur base de données: ' . $dbError->getMessage());
            // Continuer même si la base échoue
        }

        Log::info('=== SUCCÈS - Token généré ===');

        // ✅ SUCCÈS - Retourner le token pour les tests (sans envoyer d'email)
        return response()->json([
            'success' => true,
            'message' => 'Code de réinitialisation généré avec succès',
            'reset_token' => $token, // Le token à utiliser pour les tests
            'user_name' => $user->name ?? 'Utilisateur',
            'debug_info' => 'En production, ce code serait envoyé par email'
        ]);

    } catch (\Exception $e) {
        Log::error('=== ERREUR FORGOT PASSWORD ===');
        Log::error('Error: ' . $e->getMessage());
        Log::error('File: ' . $e->getFile());
        Log::error('Line: ' . $e->getLine());

        return response()->json([
            'success' => false,
            'message' => 'Erreur temporaire du serveur. Veuillez réessayer.'
        ], 500);
    }
}    /**
     * Vérifier la validité d'un token
     */
   public function verifyToken(Request $request)
{
    try {
        Log::info('=== VÉRIFICATION TOKEN ===');
        Log::info('Email: ' . $request->email);
        Log::info('Token reçu: ' . $request->token);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Récupérer le token stocké
        $resetData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        Log::info('Token en base: ' . ($resetData ? 'EXISTE' : 'NEXISTE PAS'));

        if (!$resetData) {
            Log::warning('Aucun token trouvé pour cet email');
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré'
            ], 400);
        }

        // Vérifier si le token correspond
        Log::info('Token stocké (hash): ' . $resetData->token);
        Log::info('Token reçu: ' . $request->token);
        
        $isValid = Hash::check($request->token, $resetData->token);
        Log::info('Token valide: ' . ($isValid ? 'OUI' : 'NON'));

        if (!$isValid) {
            Log::warning('Token invalide pour email: ' . $request->email);
            return response()->json([
                'success' => false,
                'message' => 'Code incorrect'
            ], 400);
        }

        // Vérifier l'expiration (24 heures)
        $isExpired = Carbon::parse($resetData->created_at)->addHours(24)->isPast();
        Log::info('Token expiré: ' . ($isExpired ? 'OUI' : 'NON'));

        if ($isExpired) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            Log::info('Token expiré supprimé pour: ' . $request->email);
            return response()->json([
                'success' => false,
                'message' => 'Code expiré'
            ], 400);
        }

        Log::info('=== TOKEN VALIDÉ AVEC SUCCÈS ===');

        return response()->json([
            'success' => true,
            'message' => 'Code valide'
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur vérification token: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json([
            'success' => false,
            'message' => 'Erreur de vérification'
        ], 500);
    }
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

        // Mettre à jour le mot de passe
        $user = User::where('email', $request->email)->first();
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

        // Utiliser auth()->user() qui est plus fiable
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
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
