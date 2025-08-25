<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('model_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('status')->default('queued'); // queued, running, succeeded, failed
            $table->unsignedTinyInteger('progress')->nullable(); // 0-100
            $table->string('status_text')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['full_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_installations');
    }
};
