<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('phone_pe_logs', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_order_id')->nullable()->index();
            $table->string('phonepe_order_id')->nullable()->index();
            $table->string('transaction_id')->nullable();
            $table->string('merchant_refund_id')->nullable()->index();
            $table->string('refund_id')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->string('currency')->default('INR');
            $table->string('status')->default('PENDING');
            $table->string('event_type')->nullable();
            $table->string('payment_instrument_type')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->string('signature')->unique()->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_pe_logs');
    }
};
