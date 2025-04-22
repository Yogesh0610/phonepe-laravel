<?php

namespace Yogeshgupta\PhonepeLaravel;

use Illuminate\Support\Facades\Storage;

class PhonePePayment
{
    private string $environment;
    private string $client_id;
    private string $client_version;
    private string $client_secret;
    private string $grant_type = 'client_credentials';
    private string $base_url;
    private string $auth_url;
    private string $token_cache_file;
    private array $staticPaymentFlow;

    public function __construct()
    {
        $this->environment = config('phonepe.environment', 'uat');
        if (!in_array($this->environment, ['uat', 'prod'])) {
            throw new \InvalidArgumentException('Invalid environment specified. Use "uat" or "prod".');
        }

        // Set environment-specific configurations
        $config = config("phonepe.{$this->environment}");
        $this->client_id = $config['client_id'] ?? '';
        $this->client_version = $config['client_version'] ?? '';
        $this->client_secret = $config['client_secret'] ?? '';
        $this->base_url = $config['base_url'] ?? '';
        $this->auth_url = $config['auth_url'] ?? '';
        $this->token_cache_file = config('phonepe.token_cache_path', 'phonepe/token_cache.json');

        // Validate required configuration
        $requiredConfigs = ['client_id', 'client_version', 'client_secret', 'base_url', 'auth_url'];
        foreach ($requiredConfigs as $key) {
            if (empty($this->$key)) {
                throw new \InvalidArgumentException("Missing required configuration: phonepe.{$this->environment}.{$key}");
            }
        }

        // Ensure the redirect URL is set
        if (!config('phonepe.redirect_url')) {
            throw new \InvalidArgumentException('Redirect URL is not set in the configuration.');
        }

        // Ensure the token cache directory is writable
        $cacheDir = dirname(storage_path($this->token_cache_file));
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true)) {
            throw new \RuntimeException('Failed to create token cache directory: ' . $cacheDir);
        }
        if (!is_writable($cacheDir)) {
            throw new \RuntimeException('Token cache directory is not writable: ' . $cacheDir);
        }

        // Validate token cache file if it exists
        if (Storage::exists($this->token_cache_file)) {
            $content = Storage::get($this->token_cache_file);
            if (!empty($content) && json_decode($content, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Token cache file is not a valid JSON file: ' . $this->token_cache_file);
            }
        }

        // Define static paymentFlow configuration
        $this->staticPaymentFlow = [
            'paymentFlow' => [
                'type' => 'PG_CHECKOUT',
                'merchantUrls' => [
                    'redirectUrl' => config('phonepe.redirect_url'),
                ],
            ],
        ];
    }

    private function getCachedToken(): ?array
    {
        try {
            if (Storage::exists($this->token_cache_file)) {
                $tokenData = json_decode(Storage::get($this->token_cache_file), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    \Log::error('PhonePe: Token cache JSON decode error: ' . json_last_error_msg());
                    return null;
                }

                // Check if token exists and hasn't expired (with 60-second buffer)
                if (
                    isset($tokenData['access_token'], $tokenData['expires_at']) &&
                    $tokenData['expires_at'] > (time() + 60)
                ) {
                    return $tokenData;
                }
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('PhonePe: Token cache read error: ' . $e->getMessage());
            return null;
        }
    }

    private function saveToken(array $tokenData): void
    {
        try {
            Storage::put($this->token_cache_file, json_encode($tokenData));
        } catch (\Exception $e) {
            \Log::error('PhonePe: Token cache write error: ' . $e->getMessage());
        }
    }

    public function getAccessToken(): ?array
    {
        // Check for cached token
        $cachedToken = $this->getCachedToken();
        if ($cachedToken) {
            return $cachedToken;
        }

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->auth_url . '/v1/oauth/token',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_id' => $this->client_id,
                    'client_version' => $this->client_version,
                    'client_secret' => $this->client_secret,
                    'grant_type' => $this->grant_type,
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);

            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                throw new \Exception('cURL error: ' . curl_error($curl));
            }
            curl_close($curl);

            $tokenData = json_decode($response, true);
            if ($tokenData === null || json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!isset($tokenData['access_token']) || empty($tokenData['access_token'])) {
                throw new \Exception('Failed to obtain access token: ' . ($tokenData['error'] ?? 'Unknown error'));
            }

            $tokenData = [
                'access_token' => $tokenData['access_token'],
                'expires_at' => isset($tokenData['expires_in']) ? time() + $tokenData['expires_in'] : time() + 3600,
            ];

            // Cache the new token
            $this->saveToken($tokenData);
            return $tokenData;
        } catch (\Exception $e) {
            \Log::error('PhonePe: Token Error: ' . $e->getMessage());
            return null;
        }
    }

    public function initiatePayment(int $amount, string $order_id, array $payload): array
    {
        try {
            $tokenData = $this->getAccessToken();
            if (!$tokenData) {
                throw new \Exception('Unable to get access token');
            }

            // Validate payload
            if (!isset($payload['metaInfo']) || !is_array($payload['metaInfo'])) {
                throw new \Exception('Invalid payload: metaInfo is required and must be an array');
            }

            // Generate merchantOrderId if not provided
            if (!isset($payload['merchantOrderId'])) {
                $payload['merchantOrderId'] = uniqid();
            }

            // Ensure amount is set in payload
            $payload['amount'] = (int) $amount;

            // Add dynamic message to static paymentFlow
            $finalPaymentFlow = $this->staticPaymentFlow;
            $finalPaymentFlow['paymentFlow']['message'] = 'Payment for order ID: ' . $order_id;
            $finalPaymentFlow['paymentFlow']['merchantUrls']['redirectUrl'] = config('phonepe.redirect_url') . '?merchantOrderId=' . $payload['merchantOrderId'];


            // Merge the provided payload with static paymentFlow
            $finalPayload = array_merge_recursive($finalPaymentFlow, $payload);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->base_url . '/checkout/v2/pay',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($finalPayload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: O-Bearer ' . $tokenData['access_token'],
                ],
            ]);

            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                throw new \Exception('cURL error: ' . curl_error($curl));
            }
            curl_close($curl);

            $paymentInfo = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!isset($paymentInfo['redirectUrl']) || empty($paymentInfo['redirectUrl'])) {
                throw new \Exception('Invalid payment response: No redirect URL');
            }

            return [
                'success' => true,
                'orderId' => $paymentInfo['orderId'],
                'redirectUrl' => $paymentInfo['redirectUrl'],
                'merchantOrderId' => $payload['merchantOrderId'],
            ];
        } catch (\Exception $e) {
            \Log::error('PhonePe: Payment Error: ' . $e->getMessage(), ['order_id' => $order_id]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function verifyPhonePePayment(string $merchantOrderId): array
    {
        try {
            $tokenData = $this->getAccessToken();
            if (!$tokenData) {
                throw new \Exception('Unable to get access token');
            }

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->base_url . '/checkout/v2/order/' . urlencode($merchantOrderId) . '/status',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: O-Bearer ' . $tokenData['access_token'],
                ],
            ]);

            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                throw new \Exception('cURL error: ' . curl_error($curl));
            }
            curl_close($curl);

            $getInfo = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $getInfo,
            ];
        } catch (\Exception $e) {
            \Log::error('PhonePe: Payment Status Check Error for merchantOrderId ' . $merchantOrderId . ': ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
