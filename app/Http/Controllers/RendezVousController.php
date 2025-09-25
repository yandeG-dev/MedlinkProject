<?php

namespace App\Http\Controllers;

use App\Models\RendezVous;
use Illuminate\Http\Request;

class RendezVousController extends Controller
{
    // Liste des rendez-vous
    public function index()
    {
        return RendezVous::with(['patient', 'assistant', 'structure'])->get();
    }

    // Prendre un rendez-vous (par un patient)
    public function store(Request $request)
    {
        $rdv = RendezVous::create([
            'date_rdv'    => $request->date_rdv,
            'statut'      => 'planifié', // par défaut
            'patient_id'  => $request->patient_id,
            'assistant_id'=> $request->assistant_id,
            'structure_id'=> $request->structure_id,
        ]);

        return response()->json([
            'message' => 'Rendez-vous créé avec succès',
            'rdv' => $rdv
        ], 201);
    }

    // Voir un RDV
    public function show($id)
    {
        return RendezVous::with(['patient', 'assistant', 'structure'])->findOrFail($id);
    }

    // Mettre à jour un RDV (par médecin ou assistant)
    public function update(Request $request, $id)
    {
        $rdv = RendezVous::findOrFail($id);
        $rdv->update($request->all());

        return response()->json([
            'message' => 'Rendez-vous mis à jour avec succès',
            'rdv' => $rdv
        ]);
    }

    // Annuler un RDV
    public function destroy($id)
    {
        RendezVous::destroy($id);

        return response()->json(['message' => 'Rendez-vous supprimé avec succès']);
    }
}
