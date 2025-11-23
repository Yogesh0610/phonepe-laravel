<?php

use Illuminate\Support\Facades\Route;
use Yogeshgupta\PhonepeLaravel\Http\Controllers\PhonePeWebhookController;

Route::post('/webhook/phonepe', [PhonePeWebhookController::class, 'handle'])
    ->name('phonepe.webhook');