<?php

namespace App\Http\Controllers;

use App\Http\Services\ResponseService;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return ResponseService::success(Unit::with('users:id,name,email')->get(), 'Units fetched successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units',
            'operating_hours' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $unit = Unit::create($validated);

        return ResponseService::success($unit, 'Unit created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit)
    {
        return $unit->load('users:id,name,email');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units')->ignore($unit->id)],
            'operating_hours' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $unit->update($validated);

        return ResponseService::success($unit, 'Unit updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign a user to this unit.
     */
    public function assignUser(Request $request, Unit $unit)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        // Attach user to unit without duplicating
        $unit->users()->syncWithoutDetaching([$request->user_id]);

        return ResponseService::success($unit->load('users:id,name,email'), 'User assigned to unit successfully.');
    }

    /**
     * Remove a user from this unit.
     */
    public function removeUser(Request $request, Unit $unit)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $unit->users()->detach($request->user_id);

        return ResponseService::success($unit->load('users:id,name,email'), 'User removed from unit successfully.');
    }
}
