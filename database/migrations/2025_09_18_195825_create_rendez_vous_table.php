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
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('statut', ['planifie', 'annule', 'termine']);
            $table->unsignedBigInteger('patient_id');

            $table->unsignedBigInteger('medecin_id');

            $table->unsignedBigInteger('assistant_id');
            $table->unsignedBigInteger('structure_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
