<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $primaryKey = 'appointment_id';

    public $timestamps = false;

    protected $fillable = [
        'donor_id',
        'event_id',
        'appointment_date',
        'appointment_time',
        'status',
        'completed_at',
        'checked_in_at',
        'cancellation_reason',
        'created_at',
        'updated_at',
        'admin_id',
        'donation_center',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'completed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }

    public function event()
    {
        return $this->belongsTo(DonationEvent::class, 'event_id', 'event_id');
    }
}
