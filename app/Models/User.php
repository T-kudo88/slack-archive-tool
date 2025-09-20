<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // ← これにする！
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'avatar_url',
        'is_admin',
        'is_active',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_login_at',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'user_id');
    }

    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
            ->withTimestamps()
            ->withPivot(['joined_at', 'left_at']);
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'user_workspace', 'user_id', 'workspace_id')
            ->withTimestamps();
    }
}
