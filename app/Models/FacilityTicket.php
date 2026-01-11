<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityTicket extends Model
{
    protected $fillable = [
        'facility_id',
        'user_id',
        'sale_id',
        'ticket_reference',
        'customer_name',
        'customer_phone',
        'ticket_date',
        'check_in_time',
        'amount',
        'payment_method',
        'status',
        'with_boot',
        'notes'
    ];

    protected $casts = [
        'ticket_date' => 'date',
        'amount' => 'decimal:2',
        'with_boot' => 'boolean',
    ];

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Generate a unique ticket reference
     */
    public static function generateReference(): string
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', now()->toDateString())->count() + 1;
        return sprintf('FT-%s-%04d', $date, $count);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('ticket_date', [$startDate, $endDate]);
    }

    /**
     * Scope for paid tickets
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
