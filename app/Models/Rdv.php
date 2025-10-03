<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RendezVous extends Model
{
    use HasFactory;


    protected $table = 'rendez_vous';

    protected $fillable = [
        'date_rdv',
         'statut',
        'patient_id',
        'assistant_id',
          'structure_id',
    ];

    // Relations
       public function patient() {
        return $this->belongsTo(User::class, 'patient_id');
    }

    // public function assistant()
    // {
    //     return $this->belongsTo(User::class, 'assistant_id');
    // }

    // public function structure()
    // {
    //     return $this->belongsTo(Structure::class, 'structure_id');
    // }


   
    // public function medecin() {
    //     return $this->belongsTo(User::class, 'medecin_id');
    // }
}

