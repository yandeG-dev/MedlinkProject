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
     * Inscrire un nouvel utilisateur via l'API
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);

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
     * Récupérer l'utilisateur connecté
     */
    public function user(Request $request): JsonResponse
    {
        try {
            if (!$request->user()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $request->user()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }



 public function forgotPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $validator->errors()
        ], 422);
    }

    // Vérifier si l'utilisateur existe
    $user = User::where('email', $request->email)->first();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Aucun utilisateur trouvé avec cet email'
        ], 404);
    }

    // Générer un token de réinitialisation
    $token = Str::random(60);
    
    // DEBUG: Afficher le token généré
    Log::info('Token généré pour ' . $request->email . ': ' . $token);
    
    // Sauvegarder le token dans la base de données
    DB::table('password_reset_tokens')->updateOrInsert(
        ['email' => $request->email],
        [
            'token' => Hash::make($token),
            'created_at' => now()
        ]
    );

    // DEBUG: Vérifier la configuration email
    Log::info('Configuration mail: ' . config('mail.mailer'));
    Log::info('SMTP host: ' . config('mail.mailers.smtp.host'));

    try {
        // Envoyer l'email SIMPLE sans template
        Mail::send([], [], function ($message) use ($user, $token) {
            $message->to($user->email)
                    ->subject('Réinitialisation de mot de passe - Medlink')
                    ->text("Bonjour {$user->prenom},\n\nVotre code de réinitialisation: {$token}\n\nCe code expire dans 24 heures.");
        });
        
        // DEBUG: Vérifier si l'email a été envoyé
        if (count(Mail::failures()) > 0) {
            Log::error('Échec envoi email à: ' . $user->email);
            return response()->json([
                'success' => false,
                'message' => 'Échec de l\'envoi de l\'email'
            ], 500);
        }
        
        Log::info('Email envoyé avec succès à: ' . $user->email);
        
        // POUR TEST - Retournez le token dans la réponse
        return response()->json([
            'success' => true,
            'message' => 'Un email avec le code de réinitialisation a été envoyé',
            'reset_token' => $token // À utiliser pour tester reset-password
        ]);
        
    } catch (\Exception $e) {
        Log::error('Erreur envoi email: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Vérifier la validité d'un token
     */
    public function verifyToken(Request $request)
    {
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

        $resetData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetData || !Hash::check($request->token, $resetData->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré'
            ], 400);
        }

        // Vérifier si le token a expiré (24 heures)
        if (Carbon::parse($resetData->created_at)->addHours(24)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Token expiré'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token valide'
        ]);
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
