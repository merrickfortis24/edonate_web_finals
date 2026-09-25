<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodRequest extends Model
{
    public const REQUEST_TYPES = ['blood_request', 'replacement_donor'];
    public const URGENCIES = ['normal', 'urgent', 'emergency'];
    public const STATUSES = ['draft', 'open', 'in_progress', 'fulfilled', 'cancelled', 'expired'];

    protected $table = 'blood_requests';

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'facility_id',
        'request_reference',
        'patient_reference_code',
        'request_type',
        'needed_blood_type_id',
        'required_donors',
        'total_donors_needed',
        'specific_match_required',
        'specific_blood_type_required_count',
        'allow_other_blood_types',
        'allow_any_blood_type_replacement',
        'urgency',
        'status',
        'notes',
        'created_by_admin_id',
        'expires_at',
        'fulfilled_at',
        'cancelled_at',
        'fulfillment_note',
        'cancellation_reason',
    ];

    protected $casts = [
        'allow_other_blood_types' => 'boolean',
        'allow_any_blood_type_replacement' => 'boolean',
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }

    public function bloodType()
    {
        return $this->belongsTo(BloodType::class, 'needed_blood_type_id', 'blood_type_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id', 'admin_id');
    }

    public function donorInvitations()
    {
        return $this->hasMany(BloodRequestDonor::class, 'request_id', 'request_id');
    }

    public function requiredDonors(): int
    {
        return max(1, (int) ($this->required_donors ?? $this->total_donors_needed ?? 1));
    }

    public function specificMatchesRequired(): int
    {
        return max(0, (int) ($this->specific_match_required ?? $this->specific_blood_type_required_count ?? 0));
    }

    public function allowsOtherBloodTypes(): bool
    {
        return (bool) ($this->allow_other_blood_types ?? $this->allow_any_blood_type_replacement ?? false);
    }
}
