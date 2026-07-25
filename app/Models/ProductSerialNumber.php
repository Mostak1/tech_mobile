<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSerialNumber extends Model
{
    use HasFactory;

    protected $table = 'product_serial_numbers';

    protected $fillable = [
        'product_id',
        'variation_id',
        'purchase_line_id',
        'serial_no',
        'status',
        'current_location_id',
        'current_customer_id',
        'sold_sell_line_id',
        'service_job_id',
        'warranty_expiry_date',
        'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'variation_id' => 'integer',
        'purchase_line_id' => 'integer',
        'current_location_id' => 'integer',
        'current_customer_id' => 'integer',
        'sold_sell_line_id' => 'integer',
        'service_job_id' => 'integer',
        'warranty_expiry_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariant::class, 'variation_id');
    }

    public function purchaseLine()
    {
        return $this->belongsTo(PurchaseDetail::class, 'purchase_line_id');
    }

    public function currentLocation()
    {
        return $this->belongsTo(Warehouse::class, 'current_location_id');
    }

    public function currentCustomer()
    {
        return $this->belongsTo(Client::class, 'current_customer_id');
    }

    public function soldSellLine()
    {
        return $this->belongsTo(SaleDetail::class, 'sold_sell_line_id');
    }

    public function serviceJob()
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    public function latestHistory()
    {
        return $this->hasOne(SerialNumberHistory::class, 'serial_number_id')->orderBy('id', 'desc');
    }

    public function histories()
    {
        return $this->hasMany(SerialNumberHistory::class, 'serial_number_id');
    }
}
