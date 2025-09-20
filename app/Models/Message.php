<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'channel_id',
        'user_id',
        'slack_message_id',
        'text',
        'thread_ts',
        'reply_count',
        'timestamp',
        'message_type',
        'has_files',
        'reactions',
        'metadata',
    ];
    //　一括代入 (Mass Assignment) = 配列を渡してまとめて保存する仕組み
    // $fillable = 一括代入で「登録して良いカラム」をホワイトリストで指定する
    // メッセージを保存するときに一括代入でセットできる項目

    protected $casts = [
        'reactions' => 'array',
        'metadata' => 'array',
        'has_files' => 'boolean',
        'slack_message_id' => 'string',
        'timestamp' => 'string',
        'thread_ts' => 'string',
    ];
    // DBから取り出すときに型変換してくれます。

    // App\Models\Message.php

    protected $with = ['files']; // ← 自動で files を常にロード

    // リレーション: メッセージはユーザーに属する
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'slack_user_id');
        // 「メッセージは1人のユーザーに属する」
    }

    // リレーション: メッセージはチャンネルに属する
    public function channel()
    {
        return $this->belongsTo(Channel::class);
        // 「メッセージは1つのチャンネルに属する」
    }

    // リレーション: メッセージはワークスペースに属する
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
        // 「メッセージは1つのワークスペースに属する」
    }

    // リレーション: メッセージは複数のファイルを持つ
    public function files()
    {
        return $this->hasMany(SlackFile::class, 'message_id', 'slack_message_id');
    }
}
