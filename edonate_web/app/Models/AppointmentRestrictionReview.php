<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentRestrictionReview extends Model
{
    protected $table = 'appointment_restriction_reviews';

    protected $primaryKey = 'review_id';

    protected $fillable = [
        'restriction_id',
        'appeal_id',
        'admin_id',
        'action',
        'notes',
        'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function restriction()
    {
        return $this->belongsTo(AppointmentRestriction::class, 'restriction_id', 'restriction_id');
    }

    public function appeal()
    {
        return $this->belongsTo(AppointmentRestrictionAppeal::class, 'appeal_id', 'appeal_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }
}
