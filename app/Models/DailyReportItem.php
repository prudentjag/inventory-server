<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportItem extends Model
{
    protected $fillable = [
        'daily_report_id',
        'product_id',
        'opening_stock',
        'stock_received',
        'quantity_sold',
        'damages',
        'closing_stock',
    ];

    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
