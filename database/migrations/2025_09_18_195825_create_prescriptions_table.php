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
             $table->foreign('medecin_id')->references('id')->on('users')->onDelete('cascade');
             $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
             $table->foreign('structure_id')->references('id')->on('users')->onDelete('cascade');
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
