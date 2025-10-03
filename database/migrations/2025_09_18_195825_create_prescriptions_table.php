<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->date('date_prescription');
            $table->text('contenu');

            $table->unsignedBigInteger('medecin_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('structure_id');


            // $table->unsignedBigInteger('medecin_id');
            // $table->unsignedBigInteger('patient_id');
            // $table->unsignedBigInteger('structure_id');
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('medecin_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('structure_id')->constrained('structures')->onDelete('cascade');
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->enum('statut', ['active', 'expirée', 'annulée'])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
