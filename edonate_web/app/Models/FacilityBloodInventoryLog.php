<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityBloodInventoryLog extends Model
{
    protected $table = 'facility_blood_inventory_logs';

    protected $primaryKey = 'inventory_log_id';

    public $timestamps = false;

    protected $fillable = [
        'facility_id',
        'blood_type_id',
        'previous_units',
        'new_units',
        'change_amount',
        'action_type',
        'reason',
        'updated_by_admin_id',
        'related_donation_id',
        'related_blood_request_id',
        'created_at',
    ];

    protected $casts = [
        'previous_units' => 'integer',
        'new_units' => 'integer',
        'change_amount' => 'integer',
        'created_at' => 'datetime',
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
