<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Donor extends Model
{
    use HasFactory;

    protected $table = 'donors';

    protected $primaryKey = 'donor_id';

    public $timestamps = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'birthdate',
        'contact_number',
        'blood_type_id',
        'location_id',
        'date_registered',
        'verification_status',
    ];

    public function bloodType()
    {
        return $this->belongsTo(BloodType::class, 'blood_type_id', 'blood_type_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }

    public function verifications()
    {
        return $this->hasMany(DonorVerification::class, 'donor_id', 'donor_id');
    }
}
