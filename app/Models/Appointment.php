<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $primaryKey = 'appointment_id';

    public $timestamps = false;

    protected $fillable = [
        'donor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'created_at',
        'admin_id',
    ];
}
