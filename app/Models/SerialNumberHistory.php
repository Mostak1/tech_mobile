<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SerialNumberHistory extends Model
{
    use HasFactory;

    protected $table = 'serial_number_histories';

    protected $fillable = [
        'serial_number_id',
        'type',
        'reference_id',
        'from_location_id',
        'to_location_id',
        'customer_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'serial_number_id' => 'integer',
        'reference_id' => 'integer',
        'from_location_id' => 'integer',
        'to_location_id' => 'integer',
        'customer_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function serialNumber()
    {
        return $this->belongsTo(ProductSerialNumber::class, 'serial_number_id');
    }

    public function fromLocation()
    {
        return $this->belongsTo(Warehouse::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Warehouse::class, 'to_location_id');
    }

    public function customer()
    {
        return $this->belongsTo(Client::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
