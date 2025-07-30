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
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('model_name');
            $table->boolean('summary_flag')->default(false)->after('summary');
            $table->unsignedBigInteger('summary_message_id')->nullable()->after('summary_flag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('summary');
            $table->dropColumn('summary_flag');
            $table->dropColumn('summary_message_id');
        });
    }
};
