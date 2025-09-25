<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS idx_messages_ts_at_desc ON messages (ts_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_messages_channel_ts ON messages (channel_id, ts_at DESC)');
        // 文字列検索をよく使うなら pg_trgm
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_messages_text_trgm ON messages USING gin (text gin_trgm_ops)');
    }
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_messages_ts_at_desc');
        DB::statement('DROP INDEX IF EXISTS idx_messages_channel_ts');
        DB::statement('DROP INDEX IF EXISTS idx_messages_text_trgm');
    }
};
