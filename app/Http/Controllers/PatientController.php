<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

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
            'role' => 'nullable|string',
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

    
}
