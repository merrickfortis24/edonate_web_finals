<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admin extends Model
{
    protected $table = 'admins';

    protected $primaryKey = 'admin_id';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'role',
        'created_at',
    ];

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(AdminTrustedDevice::class, 'admin_id', 'admin_id');
    }
}
