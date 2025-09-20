<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SlackFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slack_file_id',
        'name',
        'title',
        'mimetype',
        'file_type',
        'pretty_type',
        'user_id',
        'channel_id',
        'message_id',           // ★ 追加
        'size',
        'url_private',
        'url_private_download',
        'thumb_64',
        'thumb_80',
        'thumb_160',
        'thumb_360',
        'thumb_480',
        'thumb_720',
        'thumb_800',
        'thumb_960',
        'thumb_1024',
        'permalink',
        'permalink_public',
        'is_external',
        'external_type',
        'is_public',
        'public_url_shared',
        'display_as_bot',
        'username',
        'timestamp',
        'local_path',             // ←追加
        'local_thumbnail_path',   // ←追加
        'download_status',        // 既にある
        'file_hash',              // 既にある
        'metadata',
    ];

    protected $casts = [
        'size' => 'integer',
        'timestamp' => 'datetime',
        'is_external' => 'boolean',
        'is_public' => 'boolean',
        'public_url_shared' => 'boolean',
        'display_as_bot' => 'boolean',
        'metadata' => 'array',
        'message_id' => 'string',
    ];

    protected $appends = ['local_file_url'];

    /**
     * リレーション: ファイルの投稿者
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * リレーション: ファイルが投稿されたチャンネル（オプション）
     * 注意: SlackFileはメッセージに添付されることが多いため、
     * 直接のチャンネル関係は必須ではない
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class)->withDefault();
    }

    /**
     * リレーション: ファイルが添付されたメッセージ（オプション）
     */
    // 修正後（1ファイル = 1メッセージ）
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id', 'slack_message_id');
    }

    /**
     * ファイルサイズを人間が読みやすい形式で取得
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes === 0) return '0 B';

        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * ローカルファイルが存在するかチェック
     */
    public function hasLocalFile(): bool
    {
        return !empty($this->local_path) && Storage::disk('r2')->exists($this->local_path);
    }

    /**
     * サムネイルが存在するかチェック
     */
    public function hasLocalThumbnail(): bool
    {
        return !empty($this->local_thumbnail_path) && Storage::disk('r2')->exists($this->local_thumbnail_path);
    }

    /**
     * ダウンロード可能かチェック
     */
    public function isDownloadable(): bool
    {
        return $this->download_status === 'completed' && $this->hasLocalFile();
    }

    /**
     * 画像ファイルかチェック
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimetype ?? '', 'image/');
    }

    /**
     * 動画ファイルかチェック
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mimetype ?? '', 'video/');
    }

    /**
     * 音声ファイルかチェック
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mimetype ?? '', 'audio/');
    }

    /**
     * 最適なサムネイルURLを取得
     */
    public function getBestThumbnailUrl(?int $preferredSize = 160): ?string
    {
        $thumbnails = [
            64 => $this->thumb_64,
            80 => $this->thumb_80,
            160 => $this->thumb_160,
            360 => $this->thumb_360,
            480 => $this->thumb_480,
            720 => $this->thumb_720,
            800 => $this->thumb_800,
            960 => $this->thumb_960,
            1024 => $this->thumb_1024,
        ];

        // 指定サイズのサムネイルがあれば返す
        if ($preferredSize && isset($thumbnails[$preferredSize]) && $thumbnails[$preferredSize]) {
            return $thumbnails[$preferredSize];
        }

        // 指定サイズより大きい最小のサムネイルを探す
        ksort($thumbnails);
        foreach ($thumbnails as $size => $url) {
            if ($url && $size >= ($preferredSize ?? 160)) {
                return $url;
            }
        }

        // それでもなければ最大のサムネイル
        krsort($thumbnails);
        foreach ($thumbnails as $url) {
            if ($url) {
                return $url;
            }
        }

        return null;
    }

    /**
     * ローカルファイルのURLを取得
     */
    public function getLocalFileUrlAttribute(): ?string
    {
        if (!$this->hasLocalFile()) {
            return null;
        }

        return Storage::disk('r2')->url($this->local_path);
    }

    /**
     * ローカルサムネイルのURLを取得
     */
    public function getLocalThumbnailUrl(): ?string
    {
        if (!$this->hasLocalThumbnail()) {
            return null;
        }

        return Storage::disk('r2')->url($this->local_thumbnail_path);
    }
}
