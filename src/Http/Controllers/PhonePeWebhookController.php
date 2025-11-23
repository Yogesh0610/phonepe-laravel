<?php

namespace Yogeshgupta\PhonepeLaravel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yogeshgupta\PhonepeLaravel\PhonePePayment;
use Yogeshgupta\PhonepeLaravel\Models\PhonePeLog;
use Illuminate\Support\Facades\Log;

class PhonePeWebhookController extends Controller
{
    protected PhonePePayment $phonepe;

    public function __construct(PhonePePayment $phonepe)
    {
        $this->phonepe = $phonepe;
    }

    /**
     * Handle PhonePe Webhook
     */
    public function handle(Request $request): Response
    {
        $rawPayload = $request->getContent();
        $xVerify = $request->header('X-VERIFY');
        $saltKey = config('phonepe.webhook_salt_key');
        $saltIndex = (int) config('phonepe.webhook_salt_index', 1);

        // Basic validation
        if (empty($rawPayload) || empty($xVerify) || empty($saltKey)) {
            Log::warning('PhonePe Webhook: Missing data', [
                'ip' => $request->ip(),
                'has_payload' => !empty($rawPayload),
                'has_xverify' => !empty($xVerify),
                'has_salt' => !empty($saltKey),
            ]);
            return response('Bad Request', 400);
        }

        $payload = json_decode($rawPayload, true) ?: [];
        $eventType = $payload['eventType'] ?? 'UNKNOWN';
        $signature = $payload['signature'] ?? md5($rawPayload . now());

        // Idempotency Check - Prevent Duplicate Processing
        if (PhonePeLog::where('signature', $signature)->exists()) {
            return response('OK (already processed)', 200);
        }

        // Always log incoming webhook first
        $log = PhonePeLog::create([
            'signature'       => $signature,
            'webhook_payload' => $payload,
            'ip_address'      => $request->ip(),
            'event_type'      => $eventType,
            'status'          => 'RECEIVED',
        ]);

        // Verify X-VERIFY signature
        $isValid = $this->phonepe->verifyWebhookSignature(
            base64_encode($rawPayload),
            $xVerify,
            $saltKey,
            $saltIndex
        );

        if (!$isValid) {
            $log->update([
                'status' => 'INVALID_SIGNATURE',
                'error_message' => 'X-VERIFY signature mismatch',
            ]);

            Log::warning('PhonePe Webhook: Invalid X-VERIFY', [
                'ip' => $request->ip(),
                'event' => $eventType,
            ]);

            return response('Invalid X-VERIFY', 401);
        }

        // Valid webhook — now process
        $log->update(['status' => 'VALID']);

        $data = $payload['data'] ?? [];

        match ($eventType) {
            'PAYMENT_SUCCESS' => $this->handlePaymentSuccess($data, $log),
            'PAYMENT_FAILED'  => $this->handlePaymentFailed($data, $log),
            'REFUND_SUCCESS'  => $this->handleRefundSuccess($data, $log),
            'REFUND_FAILED'   => $this->handleRefundFailed($data, $log),
            default => Log::info('PhonePe Webhook: Unhandled event type', [
                'event' => $eventType,
                'payload' => $payload,
            ]),
        };

        return response('OK', 200);
    }

    private function handlePaymentSuccess(array $data, PhonePeLog $log): void
    {
        $log->update([
            'merchant_order_id'       => $data['merchantOrderId'] ?? null,
            'phonepe_order_id'        => $data['orderId'] ?? null,
            'transaction_id'          => $data['transactionId'] ?? null,
            'amount'                  => $data['amount'] ?? null,
            'payment_instrument_type' => $data['paymentInstrument']['type'] ?? null,
            'status'                  => 'COMPLETED',
            'processed_at'            => now(),
        ]);

        Log::info('PhonePe Payment Success', [
            'merchantOrderId' => $data['merchantOrderId'],
            'transactionId' => $data['transactionId'],
            'amount' => $data['amount'] / 100,
        ]);

        // Dispatch event for your app to handle (e.g., mark order as paid)
        event(new \Yogeshgupta\PhonepeLaravel\Events\PhonePePaymentSuccess($log));
    }

    private function handlePaymentFailed(array $data, PhonePeLog $log): void
    {
        $log->update([
            'merchant_order_id' => $data['merchantOrderId'] ?? null,
            'status'            => 'FAILED',
            'error_message'     => $data['errorMessage'] ?? 'Payment failed',
            'processed_at'      => now(),
        ]);

        Log::warning('PhonePe Payment Failed', $data);

       event(new \Yogeshgupta\PhonepeLaravel\Events\PhonePePaymentFailed($log));
    }

    private function handleRefundSuccess(array $data, PhonePeLog $log): void
    {
        $log->update([
            'merchant_refund_id' => $data['merchantRefundId'] ?? null,
            'refund_id'          => $data['refundId'] ?? null,
            'amount'             => $data['amount'] ?? null,
            'status'             => 'REFUNDED',
            'processed_at'       => now(),
        ]);

        Log::info('PhonePe Refund Success', $data);

        event(new \Yogeshgupta\PhonepeLaravel\Events\PhonePeRefundSuccess($log));
    }

    private function handleRefundFailed(array $data, PhonePeLog $log): void
    {
        $log->update([
            'merchant_refund_id' => $data['merchantRefundId'] ?? null,
            'status'             => 'REFUND_FAILED',
            'error_message'      => $data['errorMessage'] ?? 'Refund failed',
            'processed_at'       => now(),
        ]);

        Log::warning('PhonePe Refund Failed', $data);

        event(new \Yogeshgupta\PhonepeLaravel\Events\PhonePeRefundFailed($log));
    }
}
