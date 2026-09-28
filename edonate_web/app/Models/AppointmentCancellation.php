<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentCancellation extends Model
{
    protected $table = 'appointment_cancellations';

    protected $primaryKey = 'cancellation_id';

    protected $fillable = [
        'donor_id',
        'appointment_id',
        'cancelled_at',
        'reason',
        'cancelled_by',
        'consecutive_count',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }
}
