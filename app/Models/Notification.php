<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'notifications';

    protected $primaryKey = 'notification_id';

    public $timestamps = false;

    protected $fillable = [
        'donor_id',
        'title',
        'message',
        'notification_type',
        'channel',
        'recipient_type',
        'recipient_id',
        'related_type',
        'related_id',
        'is_read',
        'read_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'donor_id' => 'integer',
        'recipient_id' => 'integer',
        'related_id' => 'integer',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }
}
