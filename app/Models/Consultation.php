<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'medecin_id',
        'patient_id',
        'structure_id',
        'date_consultation',
        'motif',
        'diagnostic',
        'traitement',
        'notes',
        'prix',
        'statut'
    ];

    protected $casts = [
        'date_consultation' => 'datetime',
        'prix' => 'decimal:2'
    ];

    // Relation avec le médecin
    public function medecin()
    {
        return $this->belongsTo(User::class, 'medecin_id');
    }

    // Relation avec le patient
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }


    // Relation avec la structure
    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }
}