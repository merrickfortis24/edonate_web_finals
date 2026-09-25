<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonorAuthentication extends Model
{
    use HasFactory;

    protected $table = 'donor_authentication';

    protected $primaryKey = 'auth_id';

    public $timestamps = false;

    protected $fillable = [
        'donor_id',
        'email',
        'password',
        'is_verified',
        'verification_token',
        'verification_sent_at',
        'verified_at',
        'created_at',
    ];
}
