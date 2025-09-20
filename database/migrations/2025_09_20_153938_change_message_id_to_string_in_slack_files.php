<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slack_files', function (Blueprint $table) {
            // まず外部キー制約を削除
            $table->dropForeign(['message_id']);
        });

        Schema::table('slack_files', function (Blueprint $table) {
            // bigint → string へ変更
            $table->string('message_id', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('slack_files', function (Blueprint $table) {
            $table->bigInteger('message_id')->change();

            // 外部キーを元に戻す（messages.id に紐づいている想定）
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
        });
    }
};
