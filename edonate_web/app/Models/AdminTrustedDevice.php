<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTrustedDevice extends Model
{
    protected $table = 'admin_trusted_devices';

    protected $fillable = [
        'admin_id',
        'token_hash',
        'device_name',
        'browser',
        'platform',
        'ip_address',
        'user_agent',
        'trusted_at',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'trusted_at' => 'datetime',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }
}
