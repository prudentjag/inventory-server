<?php

namespace App\Http\Controllers;

use App\Http\Services\ResponseService;
use App\Models\Facility;
use App\Models\FacilityTicket;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacilityTicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request)
    {
        $query = FacilityTicket::with(['facility', 'user', 'sale']);

        // Filter by date
        if ($request->has('date')) {
            $query->where('ticket_date', $request->date);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Filter by facility
        if ($request->has('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->orderBy('ticket_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return ResponseService::success($tickets, 'Tickets retrieved successfully');
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'ticket_date' => 'required|date',
            'check_in_time' => 'nullable|date_format:H:i',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,card',
            'with_boot' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $facility = Facility::findOrFail($validated['facility_id']);

        // Check if facility is active
        if (!$facility->is_active) {
            return ResponseService::error(
                'This facility is currently not available',
                422
            );
        }

        return DB::transaction(function () use ($validated, $request, $facility) {
            $ticketReference = FacilityTicket::generateReference();

            // Create the ticket
            $ticket = FacilityTicket::create([
                'facility_id' => $validated['facility_id'],
                'user_id' => $request->user()->id,
                'ticket_reference' => $ticketReference,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'ticket_date' => $validated['ticket_date'],
                'check_in_time' => $validated['check_in_time'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'with_boot' => $validated['with_boot'] ?? false,
                'status' => 'paid',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Create Sale record for transaction tracking
            $sale = Sale::create([
                'unit_id' => $facility->unit_id ?? 1,
                'user_id' => $request->user()->id,
                'invoice_number' => $ticketReference,
                'total_amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'paid',
            ]);

            // Link sale to ticket
            $ticket->update(['sale_id' => $sale->id]);

            return ResponseService::success(
                $ticket->load(['facility', 'user', 'sale']),
                'Ticket created successfully',
                201
            );
        });
    }

    /**
     * Display the specified ticket.
     */
    public function show(FacilityTicket $facilityTicket)
    {
        return ResponseService::success(
            $facilityTicket->load(['facility', 'user', 'sale']),
            'Ticket retrieved successfully'
        );
    }

    /**
     * Update the specified ticket.
     */
    public function update(Request $request, FacilityTicket $facilityTicket)
    {
        if ($facilityTicket->status === 'refunded') {
            return ResponseService::error(
                'Cannot update a refunded ticket',
                422
            );
        }

        $validated = $request->validate([
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'with_boot' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $facilityTicket->update($validated);

        return ResponseService::success(
            $facilityTicket->load(['facility', 'user', 'sale']),
            'Ticket updated successfully'
        );
    }

    /**
     * Refund a ticket.
     */
    public function refund(Request $request, FacilityTicket $facilityTicket)
    {
        if ($facilityTicket->status === 'refunded') {
            return ResponseService::error(
                'Ticket is already refunded',
                422
            );
        }

        DB::transaction(function () use ($facilityTicket) {
            $facilityTicket->update(['status' => 'refunded']);

            if ($facilityTicket->sale) {
                $facilityTicket->sale->update(['payment_status' => 'refunded']);
            }
        });

        return ResponseService::success(
            $facilityTicket->load(['facility', 'user', 'sale']),
            'Ticket refunded successfully'
        );
    }

    /**
     * Get ticket statistics for a facility on a specific date.
     */
    public function stats(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'facility_id' => 'nullable|exists:facilities,id',
        ]);

        $query = FacilityTicket::where('ticket_date', $request->date)
            ->where('status', 'paid');

        if ($request->has('facility_id')) {
            $query->where('facility_id', $request->facility_id);
        }

        $stats = [
            'total_tickets' => $query->count(),
            'total_revenue' => $query->sum('amount'),
            'payment_breakdown' => $query->select('payment_method')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount) as amount')
                ->groupBy('payment_method')
                ->get(),
        ];

        return ResponseService::success($stats, 'Ticket stats retrieved successfully');
    }
}
