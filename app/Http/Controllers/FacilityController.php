<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    /**
     * Display a listing of facilities.
     */
    public function index(Request $request)
    {
        $query = Facility::with('unit');

        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        $facilities = $query->orderBy('type')->orderBy('name')->get();

        return response()->json($facilities);
    }

    /**
     * Store a newly created facility.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'hourly_rate' => 'required|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'unit_id' => 'nullable|exists:units,id',
            'is_active' => 'nullable|boolean',
        ]);

        $facility = Facility::create($validated);

        return response()->json($facility, 201);
    }

    /**
     * Display the specified facility.
     */
    public function show(Facility $facility)
    {
        return response()->json($facility->load('unit'));
    }

    /**
     * Update the specified facility.
     */
    public function update(Request $request, Facility $facility)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:50',
            'description' => 'nullable|string',
            'hourly_rate' => 'sometimes|required|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'unit_id' => 'nullable|exists:units,id',
            'is_active' => 'nullable|boolean',
        ]);

        $facility->update($validated);

        return response()->json($facility);
    }

    /**
     * Remove the specified facility.
     */
    public function destroy(Facility $facility)
    {
        // Check if there are future confirmed bookings
        $hasActiveBookings = $facility->bookings()
            ->where('booking_date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'message' => 'Cannot delete facility with active future bookings'
            ], 422);
        }

        $facility->delete();

        return response()->json(['message' => 'Facility deleted successfully']);
    }

    /**
     * Get available facility types.
     */
    public function types()
    {
        return response()->json([
            ['value' => Facility::TYPE_PITCH, 'label' => 'Football Pitch'],
            ['value' => Facility::TYPE_EVENT_HALL, 'label' => 'Event Hall'],
            ['value' => Facility::TYPE_COURT, 'label' => 'Court'],
            ['value' => Facility::TYPE_CONFERENCE_ROOM, 'label' => 'Conference Room'],
        ]);
    }
}
