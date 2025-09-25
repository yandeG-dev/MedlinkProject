<?php

namespace App\Http\Controllers;

use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;


class StructureController extends Controller
{
    /**
     * Afficher la liste des structures
     */
    public function index(): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structures = Structure::with('createur')->orderBy('nom')->get();

        return response()->json(['structures' => $structures]);
    }

    /**
     * Enregistrer une nouvelle structure
     */
    public function store(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:100',
            'adresse' => 'nullable|string',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'type' => 'nullable|string|max:50',
        ]);

        $structure = Structure::create([
            'nom' => $request->nom,
            'adresse' => $request->adresse,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'type' => $request->type,
            'createur_id' => auth()->id(),
            'actif' => true,
        ]);

        return response()->json([
            'message' => 'Structure créée avec succès',
            'structure' => $structure
        ], 201);
    }

    /**
     * Afficher une structure spécifique
     */
    public function show(Structure $structure): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        return response()->json(['structure' => $structure->load('createur')]);
    }

    /**
     * Mettre à jour une structure
     */
    public function update(Request $request, Structure $structure): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $request->validate([
            'nom' => 'required|string|max:100',
            'adresse' => 'nullable|string',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'type' => 'nullable|string|max:50',

        ]);

        $structure->update([
            'nom' => $request->nom,
            'adresse' => $request->adresse,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Structure modifiée avec succès',
            'structure' => $structure
        ]);
    }

    /**
     * Activer/désactiver une structure
     */
    public function toggle(Structure $structure): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        $structure->update([
            'actif' => !$structure->actif
        ]);

        $message = $structure->actif ? 'Structure activée' : 'Structure désactivée';

        return response()->json([
            'message' => $message . ' avec succès',
            'structure' => $structure
        ]);
    }

    /**
     * Supprimer une structure
     */
    public function destroy(Structure $structure): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Accès non autorisé'], 403);
        }

        // Vérifier si la structure a des utilisateurs avant de supprimer
        if ($structure->users()->count() > 0) {
            return response()->json([
                'error' => 'Impossible de supprimer une structure avec des utilisateurs'
            ], 422);
        }

        $structure->delete();

        return response()->json(['message' => 'Structure supprimée avec succès']);
    }
}