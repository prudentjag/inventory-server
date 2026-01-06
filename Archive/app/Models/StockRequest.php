<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequest extends Model
{
    protected $fillable = [
        'unit_id', 'product_id', 'quantity', 'status',
        'requested_by', 'approved_by', 'notes'
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
