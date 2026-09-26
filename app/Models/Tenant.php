<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $connection = 'landlord';

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'subdomain',
        'database_name',
        'database_username',
        'database_password',
        'status',
    ];

    protected $hidden = [
        'database_password',
    ];

    public function activityLogs()
    {
        return $this->hasMany(TenantActivityLog::class, 'tenant_id');
    }
}
