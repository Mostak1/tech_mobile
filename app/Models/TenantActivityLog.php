<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantActivityLog extends Model
{
    use HasFactory;

    protected $connection = 'landlord';

    protected $table = 'tenant_activity_logs';

    protected $fillable = [
        'tenant_id',
        'action',
        'description',
        'ip_address',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
