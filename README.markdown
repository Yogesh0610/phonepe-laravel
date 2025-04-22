# PhonePe API v2 Laravel Integration

A Laravel package for integrating the **PhonePe API v2** payment gateway, developed by Yogesh Gupta. This is a standalone implementation designed to simplify **PhonePe payment gateway** integration in Laravel applications, supporting the **PhonePe checkout v2** API for secure and efficient transactions.

## Features

- Initiate payments using the **PhonePe API v2**.
- Verify payment status with the **PhonePe checkout v2** endpoint.
- Support for UAT and production environments.
- Token caching for efficient **PhonePe API v2** calls.
- Laravel configuration and facade for easy **Laravel PhonePe integration**.

## Requirements

- PHP &gt;= 8.1
- Laravel 9.x, 10.x, 11.x, or 12.x

## Installation

1. Install the package via Composer:

```bash
composer require yogeshgupta/phonepe-laravel
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --tag=phonepe-config
```

This will create a `config/phonepe.php` file in your Laravel project.

3. Add your PhonePe credentials to your `.env` file:

```env
# Set environment: 'uat' for testing, 'prod' for live
PHONEPE_ENV=uat

# UAT Credentials
PHONEPE_UAT_CLIENT_ID=uat-client-id-here
PHONEPE_UAT_CLIENT_VERSION=v1
PHONEPE_UAT_CLIENT_SECRET=uat-secret-key-here

# Production Credentials
PHONEPE_PROD_CLIENT_ID=prod-client-id-here
PHONEPE_PROD_CLIENT_VERSION=v1
PHONEPE_PROD_CLIENT_SECRET=prod-secret-key-here

# Redirect URL after payment
PHONEPE_REDIRECT_URL=https://your-app.com/phonepe/process
```

## Configuration

The configuration file (`config/phonepe.php`) allows you to customize:

- Environment (`uat` or `prod`)
- Client ID, version, and secret for both environments
- Token cache path
- Redirect URL after **PhonePe API v2** payment

Example configuration:

```php
return [
    'environment' => env('PHONEPE_ENV', 'uat'),
    'uat' => [
        'client_id' => env('PHONEPE_UAT_CLIENT_ID', ''),
        'client_version' => env('PHONEPE_UAT_CLIENT_VERSION', ''),
        'client_secret' => env('PHONEPE_UAT_CLIENT_SECRET', ''),
        'base_url' => 'https://api-preprod.phonepe.com/apis/pg-sandbox',
        'auth_url' => 'https://api-preprod.phonepe.com/apis/pg-sandbox',
        'token_cache_path' => storage_path('app/phonepe/phonepe_token_uat.json'),
    ],
    'prod' => [
        // ...
    ],
];
```

## Usage

### Initiating a Payment

```php
use Yogeshgupta\PhonepeLaravel\Facades\PhonePe;

 $result = PhonePe::initiatePayment($amount, $subscription_id, $payload);

if ($result['success']) {
    return redirect($result['redirectUrl']);
} else {
    \Log::error('Payment initiation failed: ' . $result['error']);
    return back()->withErrors(['error' => $result['error']]);
}
```

### Verifying Payment Status

```php
use Yogeshgupta\PhonepeLaravel\Facades\PhonePe;

$result = PhonePe::verifyPhonePePayment('merchantOrderId123');

if ($result['success']) {
    \Log::info('Payment status: ' . json_encode($result['data']));
    return response()->json($result['data']);
} else {
    \Log::error('Payment verification failed: ' . $result['error']);
    return response()->json(['error' => $result['error']], 400);
}
```

### Example Controller

```php
<?php

namespace App\Http\Controllers;

use Yogeshgupta\PhonepeLaravel\Facades\PhonePe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Initiate a PhonePe payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function initiate(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:100', // Minimum amount in paisa (1 INR)
            'order_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Payment initiation failed: Invalid request data', ['errors' => $validator->errors()]);
            return back()->withErrors($validator)->withInput();
        }

        $orderId = (string) $request->input('order_id'); // Ensure order_id is a string
        $amount = (int) ($request->input('amount') * 100); // Convert to paisa   

        // For testing, we can hardcode the values
        // $orderId = 'test_order_id_12345'; // Example order ID
        // $amount = 10000; // Example amount in paisa (100 INR)

        // Prepare payload
        $payload = [
            'merchantOrderId' => uniqid(),
            'amount' => $amount,
            'expireAfter' => 1200,
            'metaInfo' => [
                'udf1' => 'subscription_payment',
                'udf2' => 'order_id_' . $orderId,
                'udf3' => 'student_checkout',
                'udf4' => '',
                'udf5' => '',
            ],
        ];

        try {
            $result = PhonePe::initiatePayment($amount, $orderId, $payload);

            if ($result['success']) {
                if ($request->ajax()) {
                    return response()->json(data: $result);
                }
                return redirect($result['redirectUrl']);
            }

            Log::error('Payment initiation failed', [
                'order_id' => $orderId,
                'error' => $result['error'],
            ]);
            return back()->withErrors(['error' => 'Payment initiation failed: ' . $result['error']]);
        } catch (\Exception $e) {
            Log::error('Unexpected error during payment initiation', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unexpected error: ' . $e->getMessage(),
                ], 500);
            }
            return back()->withErrors(['error' => 'An unexpected error occurred. Please try again.']);
        }
    }

    /**
     * Verify a PhonePe payment status.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'merchantOrderId' => 'required|string|min:1|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Payment verification failed: Invalid merchantOrderId', [
                'errors' => $validator->errors()->toArray(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'error' => 'Invalid merchantOrderId',
                'details' => $validator->errors()->toArray(),
            ], 400);
        }

        $merchantOrderId = $request->input('merchantOrderId');

        try {
            $result = PhonePe::verifyPhonePePayment($merchantOrderId);

            if ($result['success']) {
                Log::info('Payment status retrieved successfully', [
                    'merchantOrderId' => $merchantOrderId,
                    'data' => $result['data'],
                ]);
                return response()->json($result['data']);
            }

            Log::error('Payment verification failed', [
                'merchantOrderId' => $merchantOrderId,
                'error' => $result['error'],
            ]);
            return response()->json([
                'error' => 'Payment verification failed',
                'details' => $result['error'],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected error during payment verification', [
                'merchantOrderId' => $merchantOrderId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'An unexpected error occurred',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
```

## Testing

To run tests (if included):

```bash
vendor/bin/phpunit vendor/yogeshgupta/phonepe-laravel/tests
```

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -m 'Add your feature'`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a pull request.

## Issues

Report bugs or suggest features by opening an issue on the GitHub repository.

## License

This **Laravel PhonePe integration** package is licensed under the MIT License.
