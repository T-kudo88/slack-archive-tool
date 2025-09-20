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
        Schema::table('messages', function (Blueprint $table) {
            // Index for user-specific message queries (personal data restriction)
            $table->index(['user_id', 'channel_id', 'timestamp']);
            
            // Index for channel-user access verification
            $table->index(['channel_id', 'user_id']);
            
            // Composite index for workspace-level personal data queries
            $table->index(['workspace_id', 'user_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'channel_id', 'timestamp']);
            $table->dropIndex(['channel_id', 'user_id']);
            $table->dropIndex(['workspace_id', 'user_id', 'timestamp']);
        });
    }
};
