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
        Schema::create('structures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nom', 100);
            $table->string('adresse')->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->boolean('actif')->default(true);

            $table->foreignId('createur_id')->nullable()->constrained('users');
            $table->foreignId('structure_id')->nullable()->constrained('structures');
             $table->string('type')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('structures');
    }
};
