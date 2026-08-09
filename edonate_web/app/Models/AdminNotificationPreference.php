<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotificationPreference extends Model
{
    protected $table = 'admin_notification_preferences';

    protected $primaryKey = 'admin_notification_preference_id';

    protected $fillable = [
        'admin_id',
        'email_enabled',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }
}
