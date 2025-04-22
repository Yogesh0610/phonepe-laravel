# PhonePe API v2 Laravel Integration

A developer-friendly Laravel package for integrating the PhonePe API v2 payment gateway, supporting both redirect checkout and web iframe checkout methods. Built on top of the yogeshgupta/phonepe-laravel package by Yogesh Gupta, this package simplifies secure and efficient transaction processing using the PhonePe checkout v2 API. It includes a customizable checkout form, token caching, and seamless Laravel integration.

## Features
- **Dual Checkout Methods**:
  - **Redirect Checkout**: Redirects users to PhonePe’s payment page.
  - **Web Iframe Checkout**: Loads the payment interface in an iframe using the PhonePe Checkout SDK.
- **PhonePe API v2 Support**: Initiates and verifies payments using the latest API endpoints.
- **Developer-Friendly**:
  - Configurable default checkout method via `.env` or `config/phonepe.php`.
  - Override checkout method per request (`redirect` or `iframe`).
  - Publishable checkout form with AJAX-based payment initiation.
- **Token Caching**: Efficient API calls with cached access tokens.
- **Security**: CSRF protection, input validation, and XSS prevention.
- **Error Handling**: Detailed logging and user-friendly error messages.


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

### Example Checkout Form

A sample Blade template for the checkout form:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhonePe Payment Checkout</title>
    <style>
        .error { color: red; }
        .loading { display: none; }
        .form-group { margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Checkout</h1>
    <div id="error-messages" class="error"></div>
    <form id="payment-form">
        @csrf
        <div class="form-group">
            <label for="amount">Amount (INR):</label>
            <input type="number" name="amount" value="100" step="0.01" required>
        </div>
        <div class="form-group">
            <label for="order_id">Order ID:</label>
            <input type="text" name="order_id" value="ORD{{ rand(1000, 9999) }}" required>
        </div>
        <div class="form-group">
            <label for="uid">User ID:</label>
            <input type="text" name="uid" value="USER{{ rand(1000, 9999) }}" required>
        </div>
        <div class="form-group">
            <label for="coupon_name">Coupon Name (optional):</label>
            <input type="text" name="coupon_name" value="">
        </div>
        <button type="submit" id="pay-button">Pay Now</button>
        <span id="loading" class="loading">Processing...</span>
    </form>
    <div id="phonepe-checkout-container"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#payment-form').on('submit', function (e) {
                e.preventDefault();
                $('#pay-button').prop('disabled', true);
                $('#loading').show();
                $('#error-messages').empty();

                $.ajax({
                    url: '{{ route('phonepe.initiate') }}',
                    method: 'POST',
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_token"]').val()
                    },
                    success: function (response) {
                        $('#pay-button').prop('disabled', false);
                        $('#loading').hide();
                        if (response.success) {
                            if (response.data.checkout_method === 'redirect') {
                                window.location.href = response.data.redirectUrl;
                            } else {
                                initiatePhonePeIframeCheckout(response.data);
                            }
                        } else {
                            $('#error-messages').text('Error: ' + response.error + (response.details ? ' - ' + JSON.stringify(response.details) : ''));
                        }
                    },
                    error: function (xhr) {
                        $('#pay-button').prop('disabled', false);
                        $('#loading').hide();
                        $('#error-messages').text('Error: ' + (xhr.responseJSON?.error || 'An unexpected error occurred'));
                    }
                });
            });

            function initiatePhonePeIframeCheckout(data) {
                console.log('Initiating PhonePe Iframe Checkout with data:', data);
                var redirectUrl = '{{ route('phonepe.process') }}' +
                    '?uid=' + encodeURIComponent('{{ session('chartmonks_student_login', '') }}') +
                    '&sub_id=' + encodeURIComponent('{{ session('sub_id', '') }}') +
                    '&amount=' + encodeURIComponent('{{ session('amount', '') }}') +
                    '&coupon_name=' + encodeURIComponent('{{ session('coupon_name', '') }}') +
                    '&orderid=' + encodeURIComponent(data.orderId) +
                    '&moid=' + encodeURIComponent(data.merchantOrderId);

                var script = document.createElement('script');
                script.src = 'https://mercury.phonepe.com/web/bundle/checkout.js';
                script.onload = function () {
                    if (window.PhonePeCheckout && window.PhonePeCheckout.transact) {
                        window.PhonePeCheckout.transact({
                            tokenUrl: data.redirectUrl,
                            callback: function (response) {
                                switch (response) {
                                    case 'USER_CANCEL':
                                        $('#error-messages').text('Payment was cancelled by the user.');
                                        window.location.href = '{{ route('phonepe.checkout') }}';
                                        break;
                                    case 'CONCLUDED':
                                        window.location.href = redirectUrl;
                                        break;
                                    default:
                                        $('#error-messages').text('Payment error: ' + response);
                                        window.location.href = '{{ route('phonepe.error') }}';
                                }
                            },
                            type: 'IFRAME'
                        });
                    } else {
                        $('#error-messages').text('PhonePeCheckout is not available.');
                        window.location.href = '{{ route('phonepe.checkout') }}';
                    }
                };
                script.onerror = function () {
                    $('#error-messages').text('Failed to load PhonePe Checkout SDK.');
                    window.location.href = '{{ route('phonepe.checkout') }}';
                };
                document.head.appendChild(script);
            }
        });
    </script>
</body>
</html>
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
