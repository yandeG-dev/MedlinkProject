<?php

namespace App\Models;



use App\Models\Structure;
use App\Models\Prescription;
use App\Models\Consultation;
use App\Models\Rdv;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;



class User extends Authenticatable
{
    use HasFactory;
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Les rôles disponibles dans l'application
     */
    const ROLES = [
        'super_admin',
        'admin_structure', 
        'medecin',
        'assistant',
        'patient',
        'infirmier'
    ];

    /**
     * Groupes sanguins disponibles
     */
    const GROUPES_SANGUINS = [
        'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
<<<<<<< HEAD
   protected $fillable = [
    'nom',
    'prenom',
    'email',
    'password',
    'role',
    'telephone',
    'groupe_sanguin',
    'allergies',
    'antecedants',
    'adresse',
    'specialite',
    'age',
   
];

=======
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'role',
        'actif',
        'createur_id',
        'structure_id',
        'telephone',
        'specialite',
        'age',
        'allergies',
        'antecedants',
        'groupe_sanguin',
    ];
>>>>>>> 83d0591d81058203086873b13e0f20c60b844864

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'actif' => 'boolean',
        'age' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope pour les utilisateurs actifs
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour les utilisateurs inactifs
     */
    public function scopeInactif($query)
    {
        return $query->where('actif', false);
    }

    /**
     * Scope pour filtrer par rôle
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope pour filtrer par structure
     */
    public function scopeByStructure($query, $structureId)
    {
        return $query->where('structure_id', $structureId);
    }

    /**
     * Vérifie si l'utilisateur est un super admin
     */
   public function isSuperAdmin(): bool
    {
         return $this->role === 'super_admin';
    }

    /**
     * Vérifie si l'utilisateur est un admin de structure
     */
    public function isAdminStructure(): bool
    {
        return $this->role === 'admin_structure';
    }

    /**
     * Vérifie si l'utilisateur est un médecin
     */
    public function isMedecin(): bool
    {
        return $this->role === 'medecin';
    }

    /**
     * Vérifie si l'utilisateur est un assistant
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Vérifie si l'utilisateur est un patient
     */
    public function isPatient(): bool
    {
        return $this->role === 'patient';
    }

    /**
     * Vérifie si l'utilisateur est un infirmier
     */
    public function isInfirmier(): bool
    {
        return $this->role === 'infirmier';
    }

    /**
     * Vérifie si l'utilisateur a un rôle médical (médecin ou infirmier)
     */
    public function isMedical(): bool
    {
        return in_array($this->role, ['medecin', 'infirmier']);
    }

    /**
     * Vérifie si l'utilisateur peut gérer une structure
     */
    public function canManageStructure(): bool
    {
        return in_array($this->role, ['super_admin', 'admin_structure']);
    }

    /**
     * Relation avec la structure de l'utilisateur
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    /**
     * Relation avec le créateur de l'utilisateur (pour les admins créés par un super admin)
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    /**
     * Relation avec les utilisateurs créés par cet admin (si c'est un admin structure)
     */
    public function utilisateursCrees(): HasMany
    {
        return $this->hasMany(User::class, 'createur_id');
    }

    /**
     * Relation avec les assistants créés (pour les admins structure)
     */
    public function assistants(): HasMany
    {
        return $this->utilisateursCrees()->where('role', 'assistant');
    }

    /**
     * Relation avec les médecins créés (pour les admins structure)
     */
    public function medecins(): HasMany
    {
        return $this->utilisateursCrees()->where('role', 'medecin');
    }

    /**
     * Relation avec les prescriptions (si médecin)
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'medecin_id');
    }

    /**
     * Relation avec les consultations (si médecin)
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'medecin_id');
    }

    /**
     * Relation avec les rendez-vous (médecin ou patient)
     */
    public function rendezvous(): HasMany
    {
        return $this->hasMany(Rdv::class, 'user_id');
    }

    /**
     * Relation avec les rendez-vous en tant que médecin
     */
    public function rdvMedecin(): HasMany
    {
        return $this->hasMany(Rdv::class, 'medecin_id');
    }

    /**
     * Accessor pour le nom complet
     */
    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * Accessor pour le rôle formaté
     */
    public function getRoleFormateAttribute(): string
    {
        $roles = [
            'super_admin' => 'Super Administrateur',
            'admin_structure' => 'Administrateur de Structure',
            'medecin' => 'Médecin',
            'assistant' => 'Assistant',
            'patient' => 'Patient',
            'infirmier' => 'Infirmier'
        ];

        return $roles[$this->role] ?? $this->role;
    }

    /**
     * Vérifie si l'utilisateur peut être modifié par un super admin
     */
    public function canBeModifiedBy(User $user): bool
    {
        // Un super admin peut modifier tous les utilisateurs
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Un admin structure ne peut modifier que les utilisateurs qu'il a créés
        if ($user->isAdminStructure()) {
            return $this->createur_id === $user->id;
        }

        return false;
    }

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Empêcher la suppression des super admins
        static::deleting(function ($user) {
            if ($user->isSuperAdmin()) {
                return false;
            }
        });

        // Logique après la création
        static::created(function ($user) {
            // Logique supplémentaire si nécessaire
        });
    }

    /**
     * Méthode pour activer l'utilisateur
     */
    public function activer(): void
    {
        $this->update(['actif' => true]);
    }

    /**
     * Méthode pour désactiver l'utilisateur
     */
    public function desactiver(): void
    {
        $this->update(['actif' => false]);
    }

    /**
     * Statistiques de l'utilisateur (pour les admins structure)
     */
    public function getStatistiquesAttribute(): array
    {
        if (!$this->isAdminStructure()) {
            return [];
        }

        return [
            'assistants_total' => $this->assistants()->count(),
            'medecins_total' => $this->medecins()->count(),
            'assistants_actifs' => $this->assistants()->actif()->count(),
            'medecins_actifs' => $this->medecins()->actif()->count(),
            'utilisateurs_total' => $this->utilisateursCrees()->count(),
        ];
    }
<<<<<<< HEAD

    public function rendezVous()
{
    return $this->hasMany(RendezVous::class, 'patient_id');
}

// public function prescriptions()
// {
//     return $this->hasMany(Prescription::class, 'patient_id');
// }
   
}
=======
}
>>>>>>> 83d0591d81058203086873b13e0f20c60b844864
