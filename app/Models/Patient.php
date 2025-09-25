<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    //
    protected $fillable = [
    'nom',
    'prenom',
    'email',
    'password',
    'telephone',
    'groupe_sanguin',
    'allergies',
     'antecedants',
     'age',
     'adresse',
   
];
public function rendezVous()
{
    return $this->hasMany(RendezVous::class, 'patient_id');
}


}
