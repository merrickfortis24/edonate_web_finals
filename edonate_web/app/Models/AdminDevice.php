<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminDevice extends Model
{
    protected $table = 'admin_devices';

    protected $primaryKey = 'admin_device_id';

    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
    ];

    protected $hidden = [
        'endpoint',
        'public_key',
        'auth_token',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'user_id', 'admin_id');
    }
}
