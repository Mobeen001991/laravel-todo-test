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
        Schema::table('todos', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('priority');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
            $table->timestamp('due_at')->nullable()->after('completed_at');

            $table->index(['user_id', 'is_completed']);
            $table->index(['user_id', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_completed']);
            $table->dropIndex(['user_id', 'due_at']);
            $table->dropColumn(['is_completed', 'completed_at', 'due_at']);
        });
    }
};
