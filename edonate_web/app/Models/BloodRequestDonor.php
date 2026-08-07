<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodRequestDonor extends Model
{
    public const MATCH_TYPES = ['exact', 'replacement_any'];
    public const STATUSES = ['candidate', 'notified', 'interested', 'declined', 'confirmed', 'completed', 'contacted', 'responded', 'scheduled', 'donated'];

    protected $table = 'blood_request_donors';

    protected $primaryKey = 'id';

    protected $fillable = [
        'request_id',
        'donor_id',
        'match_type',
        'status',
        'notified_at',
        'responded_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(BloodRequest::class, 'request_id', 'request_id');
    }

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }
}
