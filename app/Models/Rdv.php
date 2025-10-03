<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rdv extends Model
{
    use HasFactory;
    protected $table = 'rendez_vous';
    protected $fillable = [
        'patient_id',
        'medecin_id',
        'structure_id',
        'date_rdv',
        'motif',
        'notes',
        'statut'
    ];

    protected $casts = [
        'date_rdv' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function medecin()
    {
        return $this->belongsTo(User::class, 'medecin_id');
    }
}