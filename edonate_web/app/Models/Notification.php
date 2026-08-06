<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $primaryKey = 'notification_id';

    public $timestamps = false;

    protected $fillable = [
        'donor_id',
        'message',
        'notification_type',
        'is_read',
        'created_at',
        'push_sent',
    ];

    protected $casts = [
        'donor_id' => 'integer',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'push_sent' => 'boolean',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }
}
