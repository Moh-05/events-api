<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShamCashService
{
    private string $token;
    private string $accountId;
    private string $baseUrl = 'https://api.shamcash-api.com/v1';

    public function __construct()
    {
        $this->token     = env('SHAMCASH_API_TOKEN');
        $this->accountId = env('SHAMCASH_ACCOUNT_ID');
    }

    public function verifyTransaction(
        string $transactionId,
        float  $expectedAmount,
        string $currency = 'SYP'
    ): array {

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept'        => 'application/json',
        ])->get("{$this->baseUrl}/transactions", [
            'account_id'      => $this->accountId,
            'transaction_ids' => $transactionId,
        ]);

        // API call failed
        if (!$response->successful()) {
            return [
                'verified' => false,
                'reason'   => 'API_ERROR',
            ];
        }

        $data         = $response->json();
        $transactions = $data['data']['transactions'] ?? [];

        if (empty($transactions)) {
            return [
                'verified' => false,
                'reason'   => 'NOT_FOUND',
            ];
        }

        $tx = $transactions[0];

        if ((float) $tx['amount'] !== (float) $expectedAmount) {
            return [
                'verified' => false,
                'reason'   => 'AMOUNT_MISMATCH',
                'expected' => $expectedAmount,
                'received' => $tx['amount'],
            ];
        }

        if ($tx['currency']['code'] !== strtoupper($currency)) {
            return [
                'verified' => false,
                'reason'   => 'CURRENCY_MISMATCH',
            ];
        }

        // reject transactions older than 60 minutes
        $occurredAt = \Carbon\Carbon::parse($tx['occurred_at']);
        if ($occurredAt->diffInMinutes(now()) > 60) {
            return [
                'verified' => false,
                'reason'   => 'TRANSACTION_EXPIRED',
            ];
        }

        return [
            'verified'     => true,
            'amount'       => $tx['amount'],
            'sender_name'  => $tx['sender_name'],
            'occurred_at'  => $tx['occurred_at'],
        ];
    }
}
