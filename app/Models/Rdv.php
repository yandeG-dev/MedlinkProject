<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rdv extends Model
{
    use HasFactory;

    protected $table = 'rendez_vous';
    
    protected $fillable = [
        'assistant_id',
        'patient_id',
        'medecin_id',
        'structure_id',
        'date_rdv', // ✅ Contient déjà la date ET l'heure
        'motif',
        'notes',
        'statut'
    ];

    protected $casts = [
        'date_rdv' => 'datetime', // ✅ Cast en datetime pour avoir date + heure
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    

    public function rdvs()
{
    return $this->hasMany(Rdv::class, 'medecin_id');
}

public function consultations()
{
    return $this->hasMany(Consultation::class, 'medecin_id');
}
    public function assistant()
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }

    public function medecin()
    {
        return $this->belongsTo(User::class, 'medecin_id');
    }

    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }
}