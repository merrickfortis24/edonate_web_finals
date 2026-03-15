<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonationRecord extends Model
{
    use HasFactory;

    protected $table = 'donation_records';

    protected $primaryKey = 'donation_id';

    public $timestamps = false;

    protected $fillable = [
        'donor_id',
        'appointment_id',
        'donation_date',
        'blood_units',
        'remarks',
    ];
}
