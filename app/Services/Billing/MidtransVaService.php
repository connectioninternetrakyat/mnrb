<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\MidtransAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Charges a virtual account through the Midtrans Core API so customers pay
 * against a REAL bank VA number issued by Midtrans (not a placeholder).
 *
 * The existing webhook (order_id == invoice_number) completes the flow by
 * flipping the invoice to lunas automatically on settlement.
 */
class MidtransVaService
{
    /**
     * Supported banks mapped to the platform's own bank codes.
     */
    public const BANKS = ['bri', 'bni', 'bca', 'mandiri'];

    /**
     * Charge a virtual account for an invoice at the given bank.
     *
     * @param  MidtransAccount  $account  Active Midtrans account (server key used).
     * @param  Invoice  $invoice  Unpaid activation invoice.
     * @param  string  $bank  bri|bni|bca|mandiri.
     * @param  string  $customerName  First name for Midtrans customer_details.
     * @param  string|null  $customerPhone  Phone for Midtrans customer_details.
     * @return array{va_number: string}
     *
     * @throws RuntimeException When the gateway rejects or times out.
     */
    public function charge(
        MidtransAccount $account,
        Invoice $invoice,
        string $bank,
        string $customerName,
        ?string $customerPhone,
    ): array {
        $bank = strtolower($bank);

        if (! in_array($bank, self::BANKS, true)) {
            throw new RuntimeException(__('Bank tidak didukung: :bank', ['bank' => $bank]));
        }

        $endpoint = rtrim(
            $account->is_production
                ? 'https://api.midtrans.com'
                : 'https://api.sandbox.midtrans.com',
            '/',
        ).'/v2/charge';

        try {
            $response = Http::timeout(20)
                ->withBasicAuth((string) $account->server_key, '')
                ->acceptJson()
                ->post($endpoint.'/charge', [
                    'payment_type' => 'bank_transfer',
                    'transaction_details' => [
                        'order_id' => $invoice->invoice_number,
                        'gross_amount' => (int) $invoice->total_exact,
                    ],
                    'bank_transfer' => [
                        'bank' => $bank,
                    ],
                    'customer_details' => [
                        'first_name' => $customerName,
                        'phone' => (string) ($customerPhone ?? ''),
                    ],
                    'custom_field1' => 'PSB',
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException(__('Gateway pembayaran tidak dapat dihubungi: :error', ['error' => $e->getMessage()]));
        }

        $payload = $response->json() ?? [];

        if (! $response->successful() || (($payload['status_code'] ?? '') !== '201')) {
            Log::warning('Midtrans VA charge rejected.', [
                'invoice' => $invoice->invoice_number,
                'status' => $response->status(),
                'body' => $payload,
            ]);

            throw new RuntimeException(__(
                'Midtrans menolak transaksi: :message',
                ['message' => (string) ($payload['status_message'] ?? $response->status())],
            ));
        }

        $vaNumber = $payload['permata_va_number']
            ?? ($payload['va_numbers'][0]['va_number'] ?? null);

        if (! is_string($vaNumber) || $vaNumber === '') {
            throw new RuntimeException(__('Midtrans tidak mengembalikan nomor VA.'));
        }

        return ['va_number' => $vaNumber];
    }
}
