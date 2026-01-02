<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'hourly_rate',
        'capacity',
        'unit_id',
        'is_active'
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Common facility types
     */
    public const TYPE_PITCH = 'pitch';
    public const TYPE_EVENT_HALL = 'event_hall';
    public const TYPE_COURT = 'court';
    public const TYPE_CONFERENCE_ROOM = 'conference_room';

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function bookings()
    {
        return $this->hasMany(FacilityBooking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
