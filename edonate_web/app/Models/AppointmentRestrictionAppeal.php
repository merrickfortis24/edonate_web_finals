<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentRestrictionAppeal extends Model
{
    protected $table = 'appointment_restriction_appeals';

    protected $primaryKey = 'appeal_id';

    protected $fillable = [
        'donor_id',
        'restriction_id',
        'justification',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'admin_notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }

    public function restriction()
    {
        return $this->belongsTo(AppointmentRestriction::class, 'restriction_id', 'restriction_id');
    }

    public function reviews()
    {
        return $this->hasMany(AppointmentRestrictionReview::class, 'appeal_id', 'appeal_id');
    }
}
