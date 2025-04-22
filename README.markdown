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
PHONEPE_ENV=uat
PHONEPE_UAT_CLIENT_ID=SU2504041946024365502022
PHONEPE_UAT_CLIENT_VERSION=1
PHONEPE_UAT_CLIENT_SECRET=ae83cba2-07c0-43a9-b0c4-1d84c261fd12
PHONEPE_PROD_CLIENT_ID=your_prod_client_id
PHONEPE_PROD_CLIENT_VERSION=your_prod_client_version
PHONEPE_PROD_CLIENT_SECRET=your_prod_client_secret
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
        'client_id' => env('PHONEPE_UAT_CLIENT_ID', 'SU2504041946024365502022'),
        'client_version' => env('PHONEPE_UAT_CLIENT_VERSION', 1),
        'client_secret' => env('PHONEPE_UAT_CLIENT_SECRET', 'ae83cba2-07c0-43a9-b0c4-1d84c261fd12'),
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

$result = PhonePe::initiatePayment(10000, 'SUB123'); // Amount in paisa, subscription ID

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
namespace App\Http\Controllers;

use Yogeshgupta\PhonepeLaravel\Facades\PhonePe;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function initiate(Request $request)
    {
        $amount = $request->input('amount'); // Amount in paisa
        $subscriptionId = $request->input('subscription_id');

        $result = PhonePe::initiatePayment($amount, $subscriptionId);

        if ($result['success']) {
            return redirect($result['redirectUrl']);
        }

        return back()->withErrors(['error' => $result['error']]);
    }

    public function verify(Request $request)
    {
        $merchantOrderId = $request->input('merchantOrderId');
        $result = PhonePe::verifyPhonePePayment($merchantOrderId);

        if ($result['success']) {
            \Log::info('Payment status: ' . json_encode($result['data']));
            return response()->json($result['data']);
        }

        \Log::error('Verification failed: ' . $result['error']);
        return response()->json(['error' => $result['error']], 400);
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
