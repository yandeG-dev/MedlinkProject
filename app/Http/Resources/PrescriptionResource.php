<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\UserResource;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contenu' => $this->contenu,
            'structure_id' => $this->structure_id,
            'instructions' => $this->instructions,
            'statut' => $this->statut,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('d/m/Y H:i'),
            'patient' => new UserResource($this->whenLoaded('patient')),
            'medecin' => new UserResource($this->whenLoaded('medecin')),
            'links' => [
                'download' => route('api.prescriptions.download', $this->id),
                'view' => route('api.prescriptions.show', $this->id)
            ]
        ];
    }
}