<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EligibilityStatus extends Model
{
    use HasFactory;

    protected $table = 'eligibility_status';

    protected $primaryKey = 'eligibility_id';

    public $timestamps = false;

    protected $fillable = [
        'donor_id',
        'last_donation_date',
        'next_eligible_date',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_notes',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }
}
