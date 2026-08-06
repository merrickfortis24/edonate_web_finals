<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationRecord extends Model
{
    use HasFactory;

    protected $table = 'donation_records';

    protected $primaryKey = 'donation_id';

    public $timestamps = false;

    protected $fillable = [
        'donation_date',
        'donation_status',
        'blood_units',
        'remarks',
        'deferred_reason',
    ];

    protected $casts = [
        'donation_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'appointment_id');
    }

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id', 'admin_id');
    }
}
