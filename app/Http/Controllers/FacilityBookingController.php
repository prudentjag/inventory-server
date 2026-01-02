<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacilityBookingController extends Controller
{
    /**
     * Display a listing of bookings.
     */
    public function index(Request $request)
    {
        $query = FacilityBooking::with(['facility', 'user', 'sale']);

        // Filter by date
        if ($request->has('date')) {
            $query->where('booking_date', $request->date);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Filter by facility
        if ($request->has('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }

        // Filter by facility type
        if ($request->has('facility_type')) {
            $query->whereHas('facility', fn($q) => $q->where('type', $request->facility_type));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $bookings = $query->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'asc')
            ->paginate(20);

        return response()->json($bookings);
    }

    /**
     * Store a newly created booking.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'payment_method' => 'required|in:cash,transfer,card',
            'notes' => 'nullable|string',
        ]);

        $facility = Facility::findOrFail($validated['facility_id']);

        // Check if facility is active
        if (!$facility->is_active) {
            return response()->json([
                'message' => 'This facility is currently not available for booking'
            ], 422);
        }

        // Check for overlapping bookings
        if (FacilityBooking::hasOverlap(
            $validated['facility_id'],
            $validated['booking_date'],
            $validated['start_time'],
            $validated['end_time']
        )) {
            return response()->json([
                'message' => 'This time slot is already booked'
            ], 422);
        }

        // Calculate total amount based on duration
        $start = \Carbon\Carbon::parse($validated['start_time']);
        $end = \Carbon\Carbon::parse($validated['end_time']);
        $hours = $end->diffInMinutes($start) / 60;
        $totalAmount = $hours * $facility->hourly_rate;

        return DB::transaction(function () use ($validated, $totalAmount, $request, $facility) {
            // Create the booking
            $booking = FacilityBooking::create([
                'facility_id' => $validated['facility_id'],
                'user_id' => $request->user()->id,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_email' => $validated['customer_email'] ?? null,
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'booking_reference' => FacilityBooking::generateReference(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create Sale record for transaction tracking
            $sale = Sale::create([
                'unit_id' => $facility->unit_id ?? 1, // Default to unit 1 if facility has no unit
                'user_id' => $request->user()->id,
                'invoice_number' => $booking->booking_reference,
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending',
            ]);

            // Link sale to booking
            $booking->update(['sale_id' => $sale->id]);

            return response()->json($booking->load(['facility', 'user', 'sale']), 201);
        });
    }

    /**
     * Display the specified booking.
     */
    public function show(FacilityBooking $facilityBooking)
    {
        return response()->json($facilityBooking->load(['facility', 'user', 'sale']));
    }

    /**
     * Update the specified booking.
     */
    public function update(Request $request, FacilityBooking $facilityBooking)
    {
        // Cannot update cancelled bookings
        if ($facilityBooking->status === 'cancelled') {
            return response()->json([
                'message' => 'Cannot update a cancelled booking'
            ], 422);
        }

        $validated = $request->validate([
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_phone' => 'sometimes|required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'booking_date' => 'sometimes|required|date|after_or_equal:today',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string',
        ]);

        // If date/time is being updated, check for overlaps
        $newDate = $validated['booking_date'] ?? \Carbon\Carbon::parse($facilityBooking->booking_date)->format('Y-m-d');
        $newStart = $validated['start_time'] ?? $facilityBooking->start_time;
        $newEnd = $validated['end_time'] ?? $facilityBooking->end_time;

        if (FacilityBooking::hasOverlap(
            $facilityBooking->facility_id,
            $newDate,
            $newStart,
            $newEnd,
            $facilityBooking->id
        )) {
            return response()->json([
                'message' => 'This time slot conflicts with another booking'
            ], 422);
        }

        // Recalculate total if times changed
        if (isset($validated['start_time']) || isset($validated['end_time'])) {
            $start = \Carbon\Carbon::parse($newStart);
            $end = \Carbon\Carbon::parse($newEnd);
            $hours = $end->diffInMinutes($start) / 60;
            $validated['total_amount'] = $hours * $facilityBooking->facility->hourly_rate;

            // Update linked sale amount
            if ($facilityBooking->sale) {
                $facilityBooking->sale->update(['total_amount' => $validated['total_amount']]);
            }
        }

        $facilityBooking->update($validated);

        return response()->json($facilityBooking->load(['facility', 'user', 'sale']));
    }

    /**
     * Confirm a booking (mark as paid).
     */
    public function confirm(Request $request, FacilityBooking $facilityBooking)
    {
        if ($facilityBooking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be confirmed'
            ], 422);
        }

        DB::transaction(function () use ($facilityBooking) {
            $facilityBooking->update(['status' => 'confirmed']);

            if ($facilityBooking->sale) {
                $facilityBooking->sale->update(['payment_status' => 'paid']);
            }
        });

        return response()->json($facilityBooking->load(['facility', 'user', 'sale']));
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Request $request, FacilityBooking $facilityBooking)
    {
        if ($facilityBooking->status === 'cancelled') {
            return response()->json([
                'message' => 'Booking is already cancelled'
            ], 422);
        }

        DB::transaction(function () use ($facilityBooking) {
            $facilityBooking->update(['status' => 'cancelled']);

            if ($facilityBooking->sale) {
                $facilityBooking->sale->update(['payment_status' => 'cancelled']);
            }
        });

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'booking' => $facilityBooking->load(['facility', 'user', 'sale'])
        ]);
    }

    /**
     * Get available time slots for a facility on a specific date.
     */
    public function availability(Request $request, Facility $facility)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->date;

        // Get all non-cancelled bookings for this facility on this date
        $bookedSlots = FacilityBooking::where('facility_id', $facility->id)
            ->where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->select('start_time', 'end_time', 'status', 'customer_name')
            ->orderBy('start_time')
            ->get();

        // Define operating hours (customize as needed)
        $operatingHours = [
            'open' => '06:00',
            'close' => '22:00',
        ];

        return response()->json([
            'facility' => $facility,
            'date' => $date,
            'operating_hours' => $operatingHours,
            'booked_slots' => $bookedSlots,
            'hourly_rate' => $facility->hourly_rate,
        ]);
    }
}
