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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');

            $table->string('channel_id');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');

            // 🔽 user_id を string に変更（SlackのユーザーIDに合わせる）
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('slack_message_id');
            $table->text('text')->nullable();
            $table->string('thread_ts')->nullable();
            $table->decimal('timestamp', 16, 6); // Slackのtsは小数
            $table->integer('reply_count')->default(0);
            $table->string('message_type')->default('message');
            $table->boolean('has_files')->default(false);
            $table->jsonb('reactions')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slack_message_id']);
            $table->index(['channel_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
