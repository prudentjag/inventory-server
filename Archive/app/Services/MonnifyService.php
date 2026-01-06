<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonnifyService
{
    protected $apiKey;
    protected $secretKey;
    protected $contractCode;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.monnify.api_key');
        $this->secretKey = config('services.monnify.secret_key');
        $this->contractCode = config('services.monnify.contract_code');
        $this->baseUrl = config('services.monnify.base_url');
    }

    /**
     * Check if credentials are configured.
     */
    public function isConfigured()
    {
        return !empty($this->apiKey) && !empty($this->secretKey) && !empty($this->contractCode);
    }

    /**
     * Get Access Token from Monnify.
     */
    public function getAccessToken()
    {
        if (!$this->isConfigured()) {
            Log::error('Monnify Credentials Missing in .env');
            return null;
        }

        $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
            ->post("{$this->baseUrl}/api/v1/auth/login");

        if ($response->successful()) {
            return $response->json('responseBody.accessToken');
        }

        Log::error('Monnify Auth Failed', ['response' => $response->json()]);
        return null;
    }

    /**
     * Create an invoice (Dynamic Virtual Account).
     * This returns the account number directly in the response.
     */
    public function createInvoice(array $data)
    {
        $token = $this->getAccessToken();
        if (!$token) return null;

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/v1/invoice/create", [
                'amount' => $data['amount'],
                'invoiceReference' => $data['payment_reference'],
                'description' => $data['description'] ?? 'Payment for Order',
                'customerName' => $data['customer_name'],
                'customerEmail' => $data['customer_email'],
                'contractCode' => $this->contractCode,
                'currencyCode' => $data['currency'] ?? 'NGN',
                'paymentMethods' => ['ACCOUNT_TRANSFER'],
                'expiryDate' => \Carbon\Carbon::now()->addDay()->format('Y-m-d H:i:s'),
                'redirectUrl' => $data['redirect_url'] ?? config('app.url'),
            ]);

        Log::info('Monnify Invoice Request', [
            'expiry' => \Carbon\Carbon::now()->addHour()->format('Y-m-d H:i:s'),
            'response' => $response->json()
        ]);
        return $response->json();
    }

    /**
     * Initialize a transaction (Hosted Checkout).
     */
    public function initializeTransaction(array $data)
    {
        $token = $this->getAccessToken();
        if (!$token) return null;

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/v1/merchant/transactions/init-transaction", [
                'amount' => $data['amount'],
                'customerName' => $data['customer_name'],
                'customerEmail' => $data['customer_email'],
                'paymentReference' => $data['payment_reference'],
                'paymentDescription' => $data['description'] ?? 'Payment for Order',
                'currencyCode' => $data['currency'] ?? 'NGN',
                'contractCode' => $this->contractCode,
                'redirectUrl' => $data['redirect_url'],
                'paymentMethods' => ['ACCOUNT_TRANSFER']
            ]);

        return $response->json();
    }

    /**
     * Verify a transaction.
     */
    public function verifyTransaction($paymentReference)
    {
        $token = $this->getAccessToken();
        if (!$token) return null;

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/api/v2/transactions/query", [
                'transactionReference' => $paymentReference
            ]);

        return $response->json();
    }
}
