<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',              // ← 必ず追加！
        'workspace_id',
        'name',
        'is_private',
        'is_dm',
        'is_mpim',
        'is_archived',
        'member_count',
        'last_synced_at',
    ];
    // 一括代入（Channel::create()）の時に保存できる項目
    //　一括代入 (Mass Assignment) = 配列を渡してまとめて保存する仕組み
    // $fillable = 一括代入で「登録して良いカラム」をホワイトリストで指定する

    // リレーション: チャンネルはワークスペースに属する
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
        // 「チャンネルは1つのワークスペースに属する」関係。
    }

    // リレーション: チャンネルは複数のメッセージを持つ
    public function messages()
    {
        return $this->hasMany(Message::class);
        // 「チャンネルは複数のメッセージを持つ」関係。
    }

    // リレーション: チャンネルは複数のユーザーを持つ（DM参加者用）
    public function users()
    {
        return $this->belongsToMany(User::class, 'channel_users')
            ->withTimestamps()
            ->withPivot(['joined_at', 'left_at']);
    }

    protected $table = 'channels';
    protected $keyType = 'string';
    public $incrementing = false;
}
