<?php

namespace YogeshGupta\PhonePe;

use Illuminate\Support\Facades\Storage;

class PhonePePayment
{
    private $environment;
    private $client_id;
    private $client_version;
    private $client_secret;
    private $grant_type = "client_credentials";
    private $base_url;
    private $auth_url;
    private $token_cache_file;

    public function __construct()
    {
        $this->environment = config('phonepe.environment', 'uat');

        // Set environment-specific configurations
        $config = config("phonepe.{$this->environment}");
        $this->client_id = $config['client_id'];
        $this->client_version = $config['client_version'];
        $this->client_secret = $config['client_secret'];
        $this->base_url = $config['base_url'];
        $this->auth_url = $config['auth_url'];
        $this->token_cache_file = $config['token_cache_path'];
    }

    private function getCachedToken()
    {
        try {
            if (Storage::exists($this->token_cache_file)) {
                $tokenData = json_decode(Storage::get($this->token_cache_file), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    \Log::error('PhonePe: Token cache JSON decode error: ' . json_last_error_msg());
                    return null;
                }

                // Check if token exists and hasn't

 expired (with 60 seconds buffer)
                if (isset($tokenData['access_token']) && isset($tokenData['expires_at']) &&
                    $tokenData['expires_at'] > (time() + 60)) {
                    return $tokenData;
                }
            }
            return null;
        } catch (\Exception $e) {
            \Log::error('PhonePe: Token cache read error: ' . $e->getMessage());
            return null;
        }
    }

    private function saveToken($tokenData)
    {
        try {
            // Ensure cache directory exists
            $cache_dir = dirname($this->token_cache_file);
            if (!is_dir($cache_dir)) {
                mkdir($cache_dir, 0755, true);
            }
            Storage::put($this->token_cache_file, json_encode($tokenData));
        } catch (\Exception $e) {
            \Log::error('PhonePe: Token cache write error: ' . $e->getMessage());
        }
    }

    public function getAccessToken()
    {
        // Check for cached token first
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
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!isset($tokenData['access_token']) || empty($tokenData['access_token'])) {
                throw new \Exception('Failed to obtain access token');
            }

            $tokenData = [
                'access_token' => $tokenData['access_token'],
                'expires_at' => $tokenData['expires_at'] ?? time() + 3600,
            ];

            // Cache the new token
            $this->saveToken($tokenData);
            return $tokenData;
        } catch (\Exception $e) {
            \Log::error('PhonePe: Token Error: ' . $e->getMessage());
            return null;
        }
    }

    public function initiatePayment($amount, $subscription_id)
    {
        try {
            $tokenData = $this->getAccessToken();
            if (!$tokenData) {
                throw new \Exception('Unable to get access token');
            }

            $morderid = uniqid();
            $payload = [
                'merchantOrderId' => $morderid,
                'amount' => (int)$amount, // Amount in paisa
                'expireAfter' => 1200,
                'metaInfo' => [
                    'udf1' => 'subscription_payment',
                    'udf2' => 'sub_id_' . $subscription_id,
                    'udf3' => 'student_checkout',
                    'udf4' => '',
                    'udf5' => '',
                ],
                'paymentFlow' => [
                    'type' => 'PG_CHECKOUT',
                    'message' => 'Payment for subscription ID: ' . $subscription_id,
                    'merchantUrls' => [
                        'redirectUrl' => config('phonepe.redirect_url', 'https://your-app.com/process'),
                    ],
                ],
            ];

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
                CURLOPT_POSTFIELDS => json_encode($payload),
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
                'merchantOrderId' => $morderid,
            ];
        } catch (\Exception $e) {
            \Log::error('PhonePe: Payment Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function verifyPhonePePayment($merchantOrderId)
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

            \Log::info('PhonePe: Payment status API raw response for merchantOrderId ' . $merchantOrderId . ': ' . $response);
            $getInfo = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            \Log::info('PhonePe: Parsed payment status response for merchantOrderId ' . $merchantOrderId . ': ' . json_encode($getInfo));
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
?>