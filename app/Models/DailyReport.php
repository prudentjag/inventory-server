<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'user_id',
        'unit_id',
        'report_date',
        'total_sales_amount',
        'total_items_sold',
        'total_stock_received',
        'total_damages',
        'remark',
        'status',
    ];

    protected $casts = [
        'report_date' => 'date',
        'total_sales_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function items()
    {
        return $this->hasMany(DailyReportItem::class);
    }
}
