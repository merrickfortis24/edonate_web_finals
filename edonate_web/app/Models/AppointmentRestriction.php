<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentRestriction extends Model
{
    protected $table = 'appointment_restrictions';

    protected $primaryKey = 'restriction_id';

    protected $fillable = [
        'donor_id',
        'status',
        'restriction_reason',
        'restricted_at',
        'restricted_by',
        'lifted_at',
        'lifted_by',
        'admin_notes',
    ];

    protected $casts = [
        'restricted_at' => 'datetime',
        'lifted_at' => 'datetime',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }

    public function appeals()
    {
        return $this->hasMany(AppointmentRestrictionAppeal::class, 'restriction_id', 'restriction_id');
    }

    public function reviews()
    {
        return $this->hasMany(AppointmentRestrictionReview::class, 'restriction_id', 'restriction_id');
    }
}
