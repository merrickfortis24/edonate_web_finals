<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use HasFactory;

    public const TYPES = ['hospital', 'clinic', 'blood_bank', 'health_center'];

    protected $table = 'facilities';

    protected $primaryKey = 'facility_id';

    protected $fillable = [
        'facility_name',
        'facility_type',
        'address',
        'barangay_name',
        'city',
        'province',
        'latitude',
        'longitude',
        'contact_number',
        'status',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function inventories()
    {
        return $this->hasMany(FacilityBloodInventory::class, 'facility_id', 'facility_id');
    }

    public function inventoryLogs()
    {
        return $this->hasMany(FacilityBloodInventoryLog::class, 'facility_id', 'facility_id');
    }

    public function bloodRequests()
    {
        return $this->hasMany(BloodRequest::class, 'facility_id', 'facility_id');
    }
}
