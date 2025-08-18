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
        Schema::create('conversation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->string('document_id'); // UUID du document dans Qdrant
            $table->timestamps();

            // Index pour accélérer les recherches
            $table->index('conversation_id');
            $table->index('document_id');

            // Garantir qu'un document n'est associé qu'une seule fois à une conversation
            $table->unique(['conversation_id', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_documents');
    }
};
