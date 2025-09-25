<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    /**
     * Statuts disponibles pour une prescription
     */
    const STATUTS = [
        'active',
        'expirée', 
        'annulée'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_id',
        'medecin_id',
        'structure_id',
        'contenu',
        'instructions',
        'notes',
        'statut',
        'date_prescription',
        'date_expiration'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_prescription' => 'datetime',
        'date_expiration' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // Ajouter les champs sensibles si nécessaire
    ];

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Définir la date de prescription par défaut
        static::creating(function ($prescription) {
            if (empty($prescription->date_prescription)) {
                $prescription->date_prescription = now();
            }
        });

        // Définir la date d'expiration par défaut (30 jours)
        static::creating(function ($prescription) {
            if (empty($prescription->date_expiration) && $prescription->statut === 'active') {
                $prescription->date_expiration = now()->addDays(30);
            }
        });
    }

    /**
     * Scope pour les prescriptions d'un médecin spécifique
     */
    public function scopeByMedecin($query, $medecinId)
    {
        return $query->where('medecin_id', $medecinId);
    }

    /**
     * Scope pour les prescriptions d'un patient spécifique
     */
    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope pour les prescriptions actives
     */
    public function scopeActive($query)
    {
        return $query->where('statut', 'active');
    }

    /**
     * Scope pour les prescriptions expirées
     */
    public function scopeExpiree($query)
    {
        return $query->where('statut', 'expirée');
    }

    /**
     * Scope pour les prescriptions annulées
     */
    public function scopeAnnulee($query)
    {
        return $query->where('statut', 'annulée');
    }

    /**
     * Scope pour les prescriptions d'une structure spécifique
     */
    public function scopeByStructure($query, $structureId)
    {
        return $query->where('structure_id', $structureId);
    }

    /**
     * Scope pour les prescriptions récentes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Relation avec le patient
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Relation avec le médecin
     */
    public function medecin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medecin_id');
    }

    /**
     * Relation avec la structure
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'structure_id');
    }

    /**
     * Relation avec les médicaments prescrits (si vous avez une table dédiée)
     */
    public function medicaments(): HasMany
    {
        return $this->hasMany(PrescriptionMedicament::class);
    }

    /**
     * Vérifie si la prescription est active
     */
    public function isActive(): bool
    {
        return $this->statut === 'active';
    }

    /**
     * Vérifie si la prescription est expirée
     */
    public function isExpiree(): bool
    {
        return $this->statut === 'expirée' || 
               ($this->date_expiration && $this->date_expiration->isPast());
    }

    /**
     * Vérifie si la prescription est annulée
     */
    public function isAnnulee(): bool
    {
        return $this->statut === 'annulée';
    }

    /**
     * Marquer la prescription comme expirée
     */
    public function marquerExpiree(): bool
    {
        return $this->update([
            'statut' => 'expirée',
            'date_expiration' => now()
        ]);
    }

    /**
     * Marquer la prescription comme annulée
     */
    public function marquerAnnulee(): bool
    {
        return $this->update([
            'statut' => 'annulée'
        ]);
    }

    /**
     * Réactiver une prescription
     */
    public function reactiver(): bool
    {
        return $this->update([
            'statut' => 'active',
            'date_expiration' => now()->addDays(30)
        ]);
    }

    /**
     * Vérifier si la prescription peut être modifiée
     */
    public function canBeModified(): bool
    {
        return $this->isActive() && !$this->isExpiree();
    }

    /**
     * Vérifier si la prescription peut être supprimée
     */
    public function canBeDeleted(): bool
    {
        return $this->isActive() || $this->isAnnulee();
    }

    /**
     * Accessor pour le statut formaté
     */
    public function getStatutFormateAttribute(): string
    {
        $statuts = [
            'active' => 'Active',
            'expirée' => 'Expirée',
            'annulée' => 'Annulée'
        ];

        return $statuts[$this->statut] ?? $this->statut;
    }

    /**
     * Accessor pour la date de prescription formatée
     */
    public function getDatePrescriptionFormateeAttribute(): string
    {
        return $this->date_prescription->format('d/m/Y H:i');
    }

    /**
     * Accessor pour la date d'expiration formatée
     */
    public function getDateExpirationFormateeAttribute(): ?string
    {
        return $this->date_expiration?->format('d/m/Y H:i');
    }




    /**
     * Méthode pour générer le contenu PDF (à implémenter avec une librairie PDF)
     */
    public function generatePdf(): string
    {
        // Implémentation basique - à adapter avec Dompdf ou une autre librairie
        $content = "
            <h1>Prescription Médicale</h1>
            <p><strong>Patient:</strong> {$this->patient->nom_complet}</p>
            <p><strong>Médecin:</strong> {$this->medecin->nom_complet}</p>
            <p><strong>Date:</strong> {$this->date_prescription_formatee}</p>
            <p><strong>Contenu:</strong> {$this->contenu}</p>
            <p><strong>Instructions:</strong> {$this->instructions}</p>
            <p><strong>Notes:</strong> {$this->notes}</p>
            <p><strong>Statut:</strong> {$this->statut_formate}</p>
        ";

        // Retourner le contenu HTML pour l'instant
        // Dans une implémentation réelle, vous utiliseriez une librairie PDF
        return $content;
    }


        /**
     * Générer un PDF de la prescription
     */
 // Dans app/Models/Prescription.php
public function getPdfData(): array
{
    return [
        'prescription' => [
            'id' => $this->id,
            'contenu' => $this->contenu,
            'instructions' => $this->instructions,
            'notes' => $this->notes,
            'statut' => $this->statut,
            'date_creation' => $this->created_at->format('d/m/Y à H:i'),
            'date_creation_iso' => $this->created_at->toISOString(),
        ],
        'patient' => [
            'nom_complet' => $this->patient->prenom . ' ' . $this->patient->nom,
            'age' => $this->patient->age,
            'telephone' => $this->patient->telephone,
            'adresse' => $this->patient->adresse,
        ],
        'medecin' => [
            'nom_complet' => 'Dr. ' . $this->medecin->prenom . ' ' . $this->medecin->nom,
            'specialite' => $this->medecin->specialite,
            'telephone' => $this->medecin->telephone,
        ],
        'structure' => [
            'nom' => $this->structure->nom,
            'telephone' => $this->structure->telephone,
            'adresse' => $this->structure->adresse,
            'email' => $this->structure->email,
        ]
    ];
}

    /**
     * Méthode pour dupliquer une prescription
     */
    public function dupliquer(array $overrides = []): Prescription
    {
        $nouvellePrescription = $this->replicate();
        $nouvellePrescription->statut = 'active';
        $nouvellePrescription->date_prescription = now();
        $nouvellePrescription->date_expiration = now()->addDays(30);
        
        // Appliquer les overrides
        foreach ($overrides as $key => $value) {
            $nouvellePrescription->$key = $value;
        }

        $nouvellePrescription->save();

        return $nouvellePrescription;
    }

    /**
     * Statistiques des prescriptions (pour les rapports)
     */
    public static function getStatistiques($structureId = null, $periode = null): array
    {
        $query = static::query();

        if ($structureId) {
            $query->where('structure_id', $structureId);
        }

        if ($periode) {
            $query->where('created_at', '>=', now()->subDays($periode));
        }

        return [
            'total' => $query->count(),
            'actives' => $query->where('statut', 'active')->count(),
            'expirees' => $query->where('statut', 'expirée')->count(),
            'annulees' => $query->where('statut', 'annulée')->count(),
            'ce_mois' => $query->whereMonth('created_at', now()->month)->count(),
        ];
    }



}