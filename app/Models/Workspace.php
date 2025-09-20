<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
// Model を継承 → データベース操作ができるようになります（Workspace::create() や Workspace::all()）。
{
    use HasFactory;
    // ファクトリ（テストデータ生成）**を使えるようにします。
    // 例：Workspace::factory()->create() でダミーのワークスペースを作れる。

    protected $fillable = [
        'slack_team_id',
        'name',
        'domain',
        'bot_token',
        'is_active',
    ];
    // 一括代入（create, update）で保存できるカラムのホワイトリスト。
    // これがないと、「意図しないカラム上書き」を防げません（セキュリティ対策）。
    //　一括代入 (Mass Assignment) = 配列を渡してまとめて保存する仕組み
    // $fillable = 一括代入で「登録して良いカラム」をホワイトリストで指定する

    // リレーション: ワークスペースは複数のチャンネルを持つ
    public function channels()
    {
        return $this->hasMany(Channel::class);
        // 1ワークスペース = 複数チャンネル の関係。
        // 例: Workspace::first()->channels → そのワークスペースの全チャンネルを取得。
    }

    // リレーション: ワークスペースは複数のメッセージを持つ
    public function messages()
    {
        return $this->hasMany(Message::class);
        // 1ワークスペース = 複数メッセージ の関係。
        // 例: Workspace::first()->messages → そのワークスペースに属する全メッセージを取得。
    }
}
