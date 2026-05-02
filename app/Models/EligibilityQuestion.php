<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EligibilityQuestion extends Model
{
    protected $table = 'eligibility_questions';

    protected $primaryKey = 'question_id';

    protected $fillable = [
        'question_text',
        'question_type',
        'is_disqualifying',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_disqualifying' => 'boolean',
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function answers()
    {
        return $this->hasMany(EligibilityAnswer::class, 'question_id', 'question_id');
    }
}
