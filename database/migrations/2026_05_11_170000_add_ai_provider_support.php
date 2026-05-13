<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->string('provider')->default('ollama')->after('id');
            $table->unsignedInteger('context_window')->nullable()->after('family');
            $table->unsignedInteger('max_output_tokens')->nullable()->after('context_window');
            $table->json('capabilities')->nullable()->after('max_output_tokens');
        });

        DB::table('models')->whereNull('provider')->update(['provider' => 'ollama']);

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('provider')->default('ollama')->after('user_id');
        });

        DB::table('conversations')->whereNull('provider')->update(['provider' => 'ollama']);

        Schema::create('user_ai_provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('label')->nullable();
            $table->text('encrypted_api_key')->nullable();
            $table->string('organization_id')->nullable();
            $table->string('project_id')->nullable();
            $table->string('status')->default('disconnected');
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_provider_accounts');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('provider');
        });

        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'context_window',
                'max_output_tokens',
                'capabilities',
            ]);
        });
    }
};
