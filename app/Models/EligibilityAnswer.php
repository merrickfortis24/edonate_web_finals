<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EligibilityAnswer extends Model
{
    protected $table = 'eligibility_answers';

    protected $primaryKey = 'answer_id';

    protected $fillable = [
        'submission_id',
        'question_id',
        'answer_value',
    ];

    public function submission()
    {
        return $this->belongsTo(EligibilitySubmission::class, 'submission_id', 'submission_id');
    }

    public function question()
    {
        return $this->belongsTo(EligibilityQuestion::class, 'question_id', 'question_id');
    }
}
