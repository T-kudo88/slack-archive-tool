<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'action',
        'resource_type',
        'resource_id',
        'accessed_user_id',
        'ip_address',
        'user_agent',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false; // Only using created_at

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($auditLog) {
            $auditLog->created_at = now();
        });
    }

    /**
     * Relationship: AuditLog belongs to admin user
     */
    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Relationship: AuditLog belongs to accessed user
     */
    public function accessedUser()
    {
        return $this->belongsTo(User::class, 'accessed_user_id');
    }

    /**
     * Scope for recent audit logs
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for specific action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific admin
     */
    public function scopeByAdmin($query, $adminId)
    {
        return $query->where('admin_user_id', $adminId);
    }
}
