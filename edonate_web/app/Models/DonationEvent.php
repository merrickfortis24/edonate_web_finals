<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationEvent extends Model
{
    use HasFactory;

    protected $table = 'donation_events';

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'title',
        'event_date',
        'start_time',
        'end_time',
        'location_name',
        'address',
        'max_capacity',
        'status',
        'created_by_admin_id',
    ];

    protected $casts = [
        'event_date' => 'date',
        'max_capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'event_id', 'event_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id', 'admin_id');
    }
}
