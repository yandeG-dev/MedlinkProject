<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'contenu',
        'patient_id',
        'medecin_id',
        'structure_id',
        'statut'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // 🔍 Relations
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function medecin()
    {
        return $this->belongsTo(User::class, 'medecin_id');
    }

    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    // 📅 Attribut dynamique : date d'expiration
    public function getDateExpirationAttribute(): string
    {
        try {
            return $this->created_at->copy()->addDays(30)->format('d/m/Y');
        } catch (\Exception $e) {
            Log::error('[Prescription] Erreur calcul date_expiration', ['id' => $this->id, 'exception' => $e->getMessage()]);
            return 'Non définie';
        }
    }

    // 🧪 Bonus : scope pour prescriptions actives
    public function scopeActives($query)
    {
        return $query->where('statut', 'active');
    }
}