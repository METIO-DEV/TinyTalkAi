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
        Schema::create('embedding_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_name')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Insérer le modèle par défaut
        DB::table('embedding_models')->insert([
            'model_name' => 'embeddinggemma:latest',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embedding_models');
    }
};
