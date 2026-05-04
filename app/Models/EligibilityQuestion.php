<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EligibilityQuestion extends Model
{
    protected $table = 'screening_questions';

    protected $primaryKey = 'question_id';

    public $timestamps = false;

    protected $fillable = [
        'question_text',
        'followup_prompt',
        'followup_trigger',
        'question_order',
        'is_active',
        'extra_data',
        'risk_level',
        'trigger_answer',
        'deferral_days',
        'recommendation_message',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'question_order' => 'integer',
        'deferral_days'  => 'integer',
    ];

    public function answers()
    {
        return $this->hasMany(DonorScreeningAnswer::class, 'question_id', 'question_id');
    }
}
