<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationEvent extends Model
{
    use HasFactory;

    protected $table = 'donation_events';

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'location_name',
        'address',
        'latitude',
        'longitude',
        'max_capacity',
        'blood_types_needed',
        'status',
        'created_by_admin_id',
    ];

    protected $casts = [
        'event_date' => 'date',
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'max_capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'event_id', 'event_id');
    }

    public function bloodTypesNeeded(): array
    {
        $raw = $this->blood_types_needed;

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded)
            ? array_values(array_filter(array_map('strval', $decoded)))
            : [];
    }
}
