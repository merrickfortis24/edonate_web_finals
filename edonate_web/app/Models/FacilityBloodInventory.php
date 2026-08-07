<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityBloodInventory extends Model
{
    protected $table = 'facility_blood_inventory';

    protected $primaryKey = 'inventory_id';

    public $timestamps = false;

    protected $fillable = [
        'facility_id',
        'blood_type_id',
        'available_units',
        'reserved_units',
        'low_stock_threshold',
        'last_updated',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'available_units' => 'integer',
        'reserved_units' => 'integer',
        'low_stock_threshold' => 'integer',
        'last_updated' => 'datetime',
    ];

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }

    public function bloodType()
    {
        return $this->belongsTo(BloodType::class, 'blood_type_id', 'blood_type_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id', 'admin_id');
    }
}
