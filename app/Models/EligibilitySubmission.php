<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EligibilitySubmission extends Model
{
    protected $table = 'eligibility_submissions';

    protected $primaryKey = 'submission_id';

    protected $fillable = [
        'donor_id',
        'submitted_at',
        'source',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }

    public function answers()
    {
        return $this->hasMany(EligibilityAnswer::class, 'submission_id', 'submission_id');
    }
}
