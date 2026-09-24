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
        'is_active',
        'profile_photo_path',
    ];

    protected $casts = [
        'blood_type_verified_at' => 'datetime',
        'birthdate' => 'date',
        'is_active' => 'boolean',
    ];

    public function bloodType()
    {
        return $this->belongsTo(BloodType::class, 'blood_type_id', 'blood_type_id');
    }

    public function bloodTypeVerifiedBy()
    {
        return $this->belongsTo(Admin::class, 'blood_type_verified_by_admin_id', 'admin_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }

    public function verifications()
    {
        return $this->hasMany(DonorVerification::class, 'donor_id', 'donor_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'donor_id', 'donor_id');
    }

    public function donationRecords()
    {
        return $this->hasMany(DonationRecord::class, 'donor_id', 'donor_id');
    }

    public function eligibilityStatuses()
    {
        return $this->hasMany(EligibilityStatus::class, 'donor_id', 'donor_id');
    }
}
