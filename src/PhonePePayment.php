<?php

namespace Yogeshgupta\PhonepeLaravel;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Yogeshgupta\PhonepeLaravel\Models\PhonePeLog;

class PhonePePayment
{
    private string $environment;
    private string $client_id;
    private string $client_version;
    private string $client_secret;
    private string $base_url;
    private string $auth_url;
    private ?string $merchant_id;
    private string $token_cache_file;
    private array $staticPaymentFlow;

    public const STATUS_PENDING   = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED    = 'FAILED';
    public const STATUS_REFUNDED  = 'REFUNDED';

    public function __construct()
    {
        $this->environment = config('phonepe.environment', 'uat');
        if (!in_array($this->environment, ['uat', 'prod'])) {
            throw new InvalidArgumentException('Environment must be "uat" or "prod".');
        }

        $config = config("phonepe.{$this->environment}");
        $required = ['client_id', 'client_version', 'client_secret', 'base_url', 'auth_url'];
        foreach ($required as $key) {
            if (empty($config[$key] ?? null)) {
                throw new InvalidArgumentException("Missing config: phonepe.{$this->environment}.{$key}");
            }
            $this->{$key} = $config[$key];
        }

        $this->merchant_id = $config['merchant_id'] ?? null;
        $this->token_cache_file = "phonepe/token_{$this->environment}.enc";

        $redirectUrl = config('phonepe.redirect_url');
        if (empty($redirectUrl)) {
            throw new InvalidArgumentException('phonepe.redirect_url is required');
        }
        if ($this->environment === 'prod' && !str_starts_with($redirectUrl, 'https://')) {
            throw new InvalidArgumentException('redirect_url must be HTTPS in production');
        }

        if (!is_dir($dir = storage_path('app/phonepe'))) {
            mkdir($dir, 0755, true);
        }

        $this->staticPaymentFlow = [
            'paymentFlow' => [
                'type' => 'PG_CHECKOUT',
                'merchantUrls' => ['redirectUrl' => $redirectUrl],
            ],
        ];
    }

    // === Token Management ===
    private function getTokenPath(): string
    {
        return storage_path('app/' . $this->token_cache_file);
    }

    private function getCachedToken(): ?array
    {
        $path = $this->getTokenPath();
        if (!file_exists($path)) return null;

        try {
            $json = Crypt::decrypt(file_get_contents($path), false);
            $data = json_decode($json, true);

            if ($data && isset($data['expires_at']) && $data['expires_at'] > time() + 120) {
                return $data;
            }
        } catch (\Exception $e) {
            Log::warning('PhonePe token decrypt failed', ['error' => $e->getMessage()]);
        }
        @unlink($path);
        return null;
    }

    private function saveToken(array $token): void
    {
        $path = $this->getTokenPath();
        $encrypted = Crypt::encrypt(json_encode($token), false);
        file_put_contents($path, $encrypted);
        @chmod($path, 0600);
    }

    public function getAccessToken(): ?array
    {
        if ($cached = $this->getCachedToken()) return $cached;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($this->auth_url, '/') . '/v1/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->client_id,
                'client_version' => $this->client_version,
                'client_secret' => $this->client_secret,
                'grant_type' => 'client_credentials',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $code !== 200) {
            Log::error('PhonePe Token Failed', ['code' => $code, 'error' => $error]);
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) return null;

        $token = [
            'access_token' => $data['access_token'],
            'expires_at' => time() + ($data['expires_in'] ?? 3600),
        ];
        $this->saveToken($token);
        return $token;
    }

    // === Initiate Payment + Auto Log ===
    public function initiatePayment(int $amount, string $order_id, array $payload = []): array
    {
        $merchantOrderId = $payload['merchantOrderId']
            ?? 'MO_' . str_replace('.', '', uniqid('', true));
        $paymentMode = config('phonepe.payment_mode', 'iframe');
        $this->log([
            'merchant_order_id' => $merchantOrderId,
            'amount' => $amount,
            'status' => self::STATUS_PENDING,
            'event_type' => 'PAYMENT_INITIATED',
            'raw_request' => ['order_id' => $order_id, 'payload' => $payload],
        ]);

        try {
            $token = $this->getAccessToken() ?? throw new Exception('Token failed');

            $payload['merchantOrderId'] = $merchantOrderId;
            $payload['amount'] = $amount;
            if (!isset($payload['metaInfo'])) $payload['metaInfo'] = [];

            $flow = $this->staticPaymentFlow;
            $flow['paymentFlow']['merchantUrls']['redirectUrl'] .= "?merchantOrderId={$merchantOrderId}";
            $flow['paymentFlow']['message'] = "Payment for Order #{$order_id}";

            $finalPayload = array_merge_recursive($flow, $payload);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->base_url . '/checkout/v2/pay',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($finalPayload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: O-Bearer ' . $token['access_token'],
                ],
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) throw new Exception('cURL: ' . $error);
            $result = json_decode($response, true);

            if (empty($result['redirectUrl'])) {
                throw new Exception($result['message'] ?? 'No redirect URL');
            }

            $this->log([
                'merchant_order_id' => $merchantOrderId,
                'phonepe_order_id' => $result['orderId'] ?? null,
                'amount' => $amount,
                'status' => self::STATUS_PENDING,
                'event_type' => 'PAYMENT_INITIATED_SUCCESS',
                'raw_response' => $result,
            ]);

            return [
                'success' => true,
                'mode' => $paymentMode,
                'merchantOrderId' => $merchantOrderId,
                'orderId' => $result['orderId'] ?? null,
                'redirectUrl' => $result['redirectUrl'],
            ];
        } catch (\Exception $e) {
            $this->log([
                'merchant_order_id' => $merchantOrderId,
                'amount' => $amount,
                'status' => self::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'event_type' => 'PAYMENT_INITIATED_FAILED',
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // === Webhook Verification ===
    public function verifyWebhook(string $rawPayload, string $xVerify, string $saltKey, int $saltIndex = 1): bool
    {
        $string = base64_encode($rawPayload) . "/pg/v1/webhook/{$saltKey}#{$saltIndex}";
        return hash_equals(hash('sha256', $string), $xVerify);
    }

    // === Status Check ===
    public function checkStatus(string $merchantOrderId): array
    {
        $token = $this->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'No token'];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . '/checkout/v2/order/' . urlencode($merchantOrderId) . '/status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: O-Bearer ' . $token['access_token']],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) return ['success' => false, 'error' => $error];

        $data = json_decode($response, true) ?: [];
        $this->log(['merchant_order_id' => $merchantOrderId, 'event_type' => 'STATUS_CHECK', 'raw_response' => $data]);

        return ['success' => true, 'data' => $data];
    }

    // === Refund ===
    public function refund(string $originalMerchantOrderId, int $amount, ?string $merchantRefundId = null): array
    {
        $merchantRefundId = $merchantRefundId ?: 'REF_' . uniqid() . '_' . time();
        $token = $this->getAccessToken() ?? throw new Exception('No token');

        $payload = [
            'merchantRefundId' => $merchantRefundId,
            'originalMerchantOrderId' => $originalMerchantOrderId,
            'amount' => $amount,
        ];

        $headers = ['Content-Type: application/json', 'Authorization: O-Bearer ' . $token['access_token']];
        if ($this->merchant_id) $headers[] = "X-MERCHANT-ID: {$this->merchant_id}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . '/payments/v2/refund',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true) ?: [];
        curl_close($ch);

        $this->log([
            'merchant_refund_id' => $merchantRefundId,
            'amount' => $amount,
            'event_type' => 'REFUND_INITIATED',
            'raw_response' => $result,
        ]);

        return $result['state'] ?? '' === 'PENDING'
            ? ['success' => true, 'merchantRefundId' => $merchantRefundId]
            : ['success' => false, 'error' => $result['errorCode'] ?? 'Refund failed'];
    }

    // === Logging Helper ===
    private function log(array $data): PhonePeLog
    {
        return PhonePeLog::create(array_merge([
            'status' => self::STATUS_PENDING,
            'currency' => 'INR',
            'ip_address' => request()->ip(),
        ], $data));
    }
    // === Webhook Signature Verification Helper ===
    public function verifyWebhookSignature(string $rawPayload, string $xVerify, string $saltKey, int $saltIndex = 1): bool
    {
        $string = base64_encode($rawPayload) . "/pg/v1/webhook/{$saltKey}#{$saltIndex}";
        return hash_equals(hash('sha256', $string), $xVerify);
    }
}
