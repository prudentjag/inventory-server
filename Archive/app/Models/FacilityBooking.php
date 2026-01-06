<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityBooking extends Model
{
    protected $fillable = [
        'facility_id',
        'user_id',
        'sale_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'booking_date',
        'start_time',
        'end_time',
        'total_amount',
        'status',
        'booking_reference',
        'notes'
    ];

    protected $casts = [
        'booking_date' => 'date',
        'total_amount' => 'decimal:2',
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
     * Generate a unique booking reference
     */
    public static function generateReference(): string
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', now()->toDateString())->count() + 1;
        return sprintf('FB-%s-%03d', $date, $count);
    }

    /**
     * Check if time slot overlaps with existing bookings
     */
    public static function hasOverlap(int $facilityId, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $query = self::where('facility_id', $facilityId)
            ->where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($q2) use ($startTime, $endTime) {
                    // New booking starts during existing booking
                    $q2->where('start_time', '<=', $startTime)
                       ->where('end_time', '>', $startTime);
                })->orWhere(function ($q2) use ($startTime, $endTime) {
                    // New booking ends during existing booking
                    $q2->where('start_time', '<', $endTime)
                       ->where('end_time', '>=', $endTime);
                })->orWhere(function ($q2) use ($startTime, $endTime) {
                    // New booking completely contains existing booking
                    $q2->where('start_time', '>=', $startTime)
                       ->where('end_time', '<=', $endTime);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('booking_date', [$startDate, $endDate]);
    }

    /**
     * Scope for confirmed bookings
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }
}
