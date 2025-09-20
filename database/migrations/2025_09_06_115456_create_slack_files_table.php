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
        Schema::create('slack_files', function (Blueprint $table) {
            $table->id();
            $table->string('slack_file_id')->unique(); // Slackファイル固有ID
            $table->string('name')->nullable(); // ファイル名
            $table->string('title')->nullable(); // ファイルタイトル
            $table->string('mimetype')->nullable(); // MIMEタイプ
            $table->string('file_type')->nullable(); // ファイルタイプ
            $table->string('pretty_type')->nullable(); // 表示用ファイルタイプ

            // 🔽 bigint → string に変更
            $table->string('user_id'); // 投稿者
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->string('channel_id')->nullable();
            $table->foreign('channel_id')
                ->references('id')
                ->on('channels')
                ->nullOnDelete(); // チャンネル

            $table->unsignedBigInteger('size')->default(0); // ファイルサイズ

            // Slack URL情報
            $table->text('url_private')->nullable();
            $table->text('url_private_download')->nullable();

            // サムネイルURL（複数サイズ）
            $table->text('thumb_64')->nullable();
            $table->text('thumb_80')->nullable();
            $table->text('thumb_160')->nullable();
            $table->text('thumb_360')->nullable();
            $table->text('thumb_480')->nullable();
            $table->text('thumb_720')->nullable();
            $table->text('thumb_800')->nullable();
            $table->text('thumb_960')->nullable();
            $table->text('thumb_1024')->nullable();

            // パーマリンク情報
            $table->text('permalink')->nullable();
            $table->text('permalink_public')->nullable();

            // ファイル属性
            $table->boolean('is_external')->default(false);
            $table->string('external_type')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('public_url_shared')->default(false);
            $table->boolean('display_as_bot')->default(false);
            $table->string('username')->nullable();

            // タイムスタンプ
            $table->timestamp('timestamp')->nullable();

            // ローカルストレージ情報
            $table->string('local_path')->nullable();
            $table->string('local_thumbnail_path')->nullable();
            $table->enum('download_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('file_hash')->nullable();

            // メタデータ
            $table->json('metadata')->nullable();

            // ソフトデリート
            $table->softDeletes();
            $table->timestamps();

            // インデックス
            $table->index(['user_id', 'created_at']);
            $table->index(['download_status', 'created_at']);
            $table->index(['file_type', 'created_at']);
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_files');
    }
};
