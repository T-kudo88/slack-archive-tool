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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // 🔽 bigint → string に変更
            $table->string('admin_user_id');
            $table->foreign('admin_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->string('action'); // 'access_user_message', 'access_dm_channel', etc.
            $table->string('resource_type'); // 'message', 'channel', 'user'
            $table->unsignedBigInteger('resource_id');

            // 🔽 bigint → string に変更
            $table->string('accessed_user_id')->nullable();
            $table->foreign('accessed_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable(); // Justification or additional context
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamp('created_at');

            // Indexes for security monitoring
            $table->index(['admin_user_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['accessed_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at'); // For time-based queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
