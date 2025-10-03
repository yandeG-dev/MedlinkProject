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
            $table->id();
             $table->date('date_rdv');
            $table->enum('statut', ['planifié', 'annulé', 'terminé'])->default('planifié');
            $table->unsignedBigInteger('patient_id');

            // $table->unsignedBigInteger('assistant_id');
            // $table->unsignedBigInteger('structure_id');
             $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
             $table->foreign('assistant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('structure_id')->references('id')->on('structures')->onDelete('cascade');

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
