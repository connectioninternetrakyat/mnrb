<?php

namespace App\Http\Controllers\Gateways;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MidtransAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Handle a Midtrans payment notification.
     *
     * The notification signature is sha512(order_id + status_code +
     * gross_amount + server_key) as documented by Midtrans. The payload is
     * verified against every active account's server key.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $signatureKey = (string) ($payload['signature_key'] ?? '');
        $account = $this->matchingAccount($payload, $signatureKey);

        if ($account === null) {
            Log::warning('Midtrans webhook rejected: no matching account signature.', [
                'order_id' => $payload['order_id'] ?? null,
            ]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $account->last_notified_at = now();
        $account->save();

        Log::info('Midtrans notification received.', [
            'account' => $account->name,
            'order_id' => $payload['order_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'fraud_status' => $payload['fraud_status'] ?? null,
            'gross_amount' => $payload['gross_amount'] ?? null,
        ]);

        // Automatic verification of activation invoices (PSB transfer/VA):
        // when the customer pays, the invoice flips to lunas.
        $this->verifyInvoice($payload);

        return response()->json(['message' => 'Notification received.']);
    }

    /**
     * Match a paid notification against an activation invoice and mark it
     * as paid automatically.
     *
     * @param  array<string, mixed>  $payload
     */
    private function verifyInvoice(array $payload): void
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $status = (string) ($payload['transaction_status'] ?? '');

        if ($orderId === '' || ! in_array($status, ['settlement', 'capture'], true)) {
            return;
        }

        if (($payload['fraud_status'] ?? null) === 'challenge') {
            return;
        }

        $invoice = Invoice::query()
            ->where('invoice_number', $orderId)
            ->where('status', InvoiceStatus::Unpaid->value)
            ->first();

        if ($invoice === null) {
            return;
        }

        // The notified amount must match the exact billed total.
        if ((float) ($payload['gross_amount'] ?? -1) !== (float) $invoice->total_exact) {
            Log::warning('Midtrans invoice amount mismatch.', [
                'invoice' => $invoice->invoice_number,
                'notified' => $payload['gross_amount'] ?? null,
                'expected' => $invoice->total_exact,
            ]);

            return;
        }

        $invoice->markAsPaid();

        Log::info('Invoice paid via Midtrans.', [
            'invoice' => $invoice->invoice_number,
        ]);
    }

    /**
     * Find the active account whose server key produces the expected signature.
     *
     * @param  array<string, mixed>  $payload
     */
    private function matchingAccount(array $payload, string $signatureKey): ?MidtransAccount
    {
        if ($signatureKey === '') {
            return null;
        }

        foreach (MidtransAccount::query()->where('is_active', true)->get() as $account) {
            $serverKey = (string) $account->server_key;

            if ($serverKey === '') {
                continue;
            }

            $expected = hash('sha512', implode('', [
                (string) ($payload['order_id'] ?? ''),
                (string) ($payload['status_code'] ?? ''),
                (string) ($payload['gross_amount'] ?? ''),
                $serverKey,
            ]));

            if (hash_equals($expected, $signatureKey)) {
                return $account;
            }
        }

        return null;
    }
}
