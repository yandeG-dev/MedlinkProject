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
    'role',
];

}
