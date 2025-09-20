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
        Schema::create('channel_users', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id');
            $table->foreign('channel_id')
                ->references('id')
                ->on('channels')
                ->onDelete('cascade');

            // 🔽 bigint → string に修正
            $table->string('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->boolean('is_admin')->default(false); // Channel admin
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // Prevent duplicate entries
            $table->unique(['channel_id', 'user_id']);

            // Index for quick lookups
            $table->index(['user_id', 'channel_id']);
            $table->index(['channel_id', 'left_at']); // Active participants
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_users');
    }
};
