<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // 外部キーを削除
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('channel_users', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('slack_files', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });

        // channels.id を bigint → string に変更
        Schema::table('channels', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropColumn('id');
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->string('id')->primary()->first();
        });

        // 外部キーを再作成
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
        Schema::table('channel_users', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
        Schema::table('slack_files', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
    }

    public function down()
    {
        // 外部キー削除
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('channel_users', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });
        Schema::table('slack_files', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });

        // channels.id を string → bigint に戻す
        Schema::table('channels', function (Blueprint $table) {
            $table->dropPrimary();
            $table->dropColumn('id');
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
        });

        // 外部キー再作成
        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
        Schema::table('channel_users', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
        Schema::table('slack_files', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
        });
    }
};
