<?php

namespace YogeshGupta\PhonePe\Tests;

use Illuminate\Support\Facades\Storage;
use YogeshGupta\PhonePe\PhonePePayment;
use Orchestra\Testbench\TestCase;

class PhonePePaymentTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return ['YogeshGupta\PhonePe\PhonePeServiceProvider'];
    }

    protected function getPackageAliases($app)
    {
        return [
            'PhonePe' => 'YogeshGupta\PhonePe\Facades\PhonePe',
        ];
    }

    public function testConfigLoadsCorrectly()
    {
        $this->assertEquals('uat', config('phonepe.environment'));
        $this->assertEquals('https://api-preprod.phonepe.com/apis/pg-sandbox', config('phonepe.uat.base_url'));
    }

    public function testTokenCachePath()
    {
        $phonePe = new PhonePePayment();
        $reflection = new \ReflectionClass($phonePe);
        $tokenCacheFile = $reflection->getProperty('token_cache_file');
        $tokenCacheFile->setAccessible(true);

        $this->assertEquals(storage_path('app/phonepe/phonepe_token_uat.json'), $tokenCacheFile->getValue($phonePe));
    }

    // Add more tests, e.g., mocking HTTP requests for initiatePayment and verifyPhonePePayment
}
?>