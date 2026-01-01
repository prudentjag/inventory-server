<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle Monnify Webhook.
     */
    public function handleMonnify(Request $request)
    {
        $payload = $request->all();
        Log::info('Monnify Webhook Received', $payload);

        // Monnify transaction hash verification (Optional but recommended)
        /*
        $secret = config('services.monnify.secret_key');
        $signature = $request->header('monnify-signature');
        $computedSignature = hash_hmac('sha512', $request->getContent(), $secret);
        if ($signature !== $computedSignature) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }
        */

        $eventData = $payload['eventData'] ?? [];
        $paymentReference = $eventData['paymentReference'] ?? null;
        $status = $payload['eventType'] ?? null;

        if ($status === 'SUCCESSFUL_TRANSACTION' && $paymentReference) {
            $sale = Sale::where('invoice_number', $paymentReference)->first();
            if ($sale) {
                $sale->update([
                    'payment_status' => 'paid',
                    'transaction_reference' => $eventData['transactionReference'] ?? null
                ]);
                Log::info("Sale #{$sale->invoice_number} marked as PAID via Monnify");
            }
        }

        return response()->json(['status' => 'success']);
    }
}
