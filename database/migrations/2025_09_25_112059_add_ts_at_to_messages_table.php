<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Slack ts を datetime に変換した列
            $table->timestampTz('ts_at')->nullable()->index();
        });

        // 既存データを一括変換
        // "1745042288.176029" -> to_timestamp(1745042288) + 0.176 秒
        DB::statement("
        UPDATE messages
        SET ts_at = to_timestamp(split_part(timestamp::text, '.', 1)::bigint)
                    + (('0.' || split_part(timestamp::text, '.', 2))::double precision * interval '1 second')
        WHERE (timestamp::text) ~ '^[0-9]+(\\.[0-9]+)?$'
    ");
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('ts_at');
        });
    }
};
