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
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medecin_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('patient_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('structure_id')->constrained('structures')->onDelete('cascade');
            $table->dateTime('date_consultation');
            $table->string('motif', 500);
            $table->text('diagnostic');
            $table->text('traitement')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('prix', 10, 2)->nullable();
            $table->enum('statut', ['planifie', 'termine', 'annule'])->default('planifie');
            $table->timestamps();

            // Index pour les performances
            $table->index('medecin_id');
            $table->index('patient_id');
            $table->index('date_consultation');
            $table->index('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
   public function down()
{
    Schema::table('consultations', function (Blueprint $table) {
        $table->dropForeign(['structure_id']);
        $table->dropColumn('structure_id');
    });
}
};