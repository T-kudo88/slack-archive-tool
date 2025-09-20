<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_workspace', function (Blueprint $table) {
            $table->id();

            // 🔽 bigint → string に変更
            $table->string('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_workspace');
    }
};
