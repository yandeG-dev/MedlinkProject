<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    //
     public function patient() {
        return $this->belongsTo(Patient::class);
    }
    public function medecin() {
        return $this->belongsTo(User::class, 'medecin_id');
    }
}
