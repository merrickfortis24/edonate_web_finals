<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonorVerification extends Model
{
    use HasFactory;

    protected $table = 'donor_verifications';

    protected $primaryKey = 'verification_id';

    protected $fillable = [
        'donor_id',
        'document_type',
        'document_path',
        'status',
        'rejection_reason',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class, 'donor_id', 'donor_id');
    }
}
