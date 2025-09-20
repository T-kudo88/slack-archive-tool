<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 外部キーを一旦削除
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('channel_users', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('slack_files', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });

        // channels.id を string に変更
        Schema::table('channels', function (Blueprint $table) {
            $table->string('id')->change();
        });

        // messages.channel_id を string に変更 & 外部キー復活
        Schema::table('messages', function (Blueprint $table) {
            $table->string('channel_id')->change();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
        });

        // channel_users.channel_id を string に変更 & 外部キー復活
        Schema::table('channel_users', function (Blueprint $table) {
            $table->string('channel_id')->change();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
        });

        // slack_files.channel_id を string に変更 & 外部キー復活
        Schema::table('slack_files', function (Blueprint $table) {
            $table->string('channel_id')->change();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('channel_users', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('slack_files', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->bigInteger('id')->change();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->bigInteger('channel_id')->change();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
        });

        Schema::table('channel_users', function (Blueprint $table) {
            $table->string('channel_id');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });

        Schema::table('slack_files', function (Blueprint $table) {
            $table->bigInteger('channel_id')->change();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
        });
    }
};
