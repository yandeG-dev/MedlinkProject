<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Vérifier si le super admin existe déjà
        // if (!User::where('email', 'superadmin@gmail.com')->exists()) {
        //     User::create([
        $user = User::updateOrCreate(
    ['email' => 'superadmin@gmail.com'],
                [
                    'nom' => 'Super',
                    'prenom' => 'Admin',
                    'email' => 'superadmin@gmail.com',
                    'password' => Hash::make('password'), // Changez ce mot de passe en production
                'role' => 'super_admin',
                'actif' => true,
                'createur_id' => null, // Le super admin n'a pas de créateur
                'structure_id' => null,
                'specialite' => null,
                'age' => null,
                'adresse' => null,
                'telephone' => null,
                'groupe_sanguin' => null,
                'antecedants' => '',
                'allergies' => '',
            ]);
            
            $this->command->info('Super Admin créé avec succès!');
            $this->command->info('Email: superadmin@gmail.com');
            $this->command->info('Mot de passe: password');
        } else {
            $this->command->info('Le Super Admin existe déjà.');
        }
    }
}