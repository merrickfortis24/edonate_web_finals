<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorScreeningAnswer extends Model
{
    protected $table = 'donor_screening_answers';

    protected $primaryKey = 'answer_id';

    public $timestamps = false;

    protected $fillable = [
        'eligibility_id',
        'question_id',
        'answer',
        'followup_answer',
    ];

    public function eligibilityStatus()
    {
        return $this->belongsTo(EligibilityStatus::class, 'eligibility_id', 'eligibility_id');
    }

    public function question()
    {
        return $this->belongsTo(EligibilityQuestion::class, 'question_id', 'question_id');
    }
}
