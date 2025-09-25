<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Structure extends Model
{
    use HasFactory;

    /**
     * Types de structures disponibles
     */
    const TYPES = [
        'hopital',
        'clinique', 
        'cabinet',
        'autre'
    ];

    protected $fillable = [
        'nom',
        'adresse',
        'telephone',
        'email',
        'type',
        'actif',
        'admin_id',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec l'administrateur de la structure
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Relation avec les utilisateurs de la structure
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relation avec les prescriptions de la structure
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * Relation avec les consultations de la structure
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /**
     * Relation avec les rendez-vous de la structure
     */
    public function rendezvous(): HasMany
    {
        return $this->hasMany(Rdv::class);
    }

    /**
     * Scope pour les structures actives
     */
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Méthode pour activer la structure
     */
    public function activer(): void
    {
        $this->update(['actif' => true]);
    }

    /**
     * Méthode pour désactiver la structure
     */
    public function desactiver(): void
    {
        $this->update(['actif' => false]);
    }

    /**
     * Accessor pour le type formaté
     */
    public function getTypeFormateAttribute(): string
    {
        $types = [
            'hopital' => 'Hôpital',
            'clinique' => 'Clinique',
            'cabinet' => 'Cabinet',
            'autre' => 'Autre'
        ];

        return $types[$this->type] ?? $this->type;
    }

    /**
     * Statistiques de la structure
     */
    public function getStatistiquesAttribute(): array
    {
        return [
            'utilisateurs_total' => $this->users()->count(),
            'medecins_total' => $this->users()->where('role', 'medecin')->count(),
            'assistants_total' => $this->users()->where('role', 'assistant')->count(),
            'patients_total' => $this->users()->where('role', 'patient')->count(),
            'prescriptions_total' => $this->prescriptions()->count(),
            'consultations_total' => $this->consultations()->count(),
            'rdv_total' => $this->rendezvous()->count(),
        ];
    }
}