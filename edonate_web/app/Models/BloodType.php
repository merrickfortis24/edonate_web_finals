<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloodType extends Model
{
    use HasFactory;

    protected $table = 'blood_types';

    protected $primaryKey = 'blood_type_id';

    public $timestamps = false;

    protected $fillable = [
        'blood_type',
    ];

    public function donors()
    {
        return $this->hasMany(Donor::class, 'blood_type_id', 'blood_type_id');
    }

    public function facilityInventories()
    {
        return $this->hasMany(FacilityBloodInventory::class, 'blood_type_id', 'blood_type_id');
    }

    public function bloodRequests()
    {
        return $this->hasMany(BloodRequest::class, 'needed_blood_type_id', 'blood_type_id');
    }
}
