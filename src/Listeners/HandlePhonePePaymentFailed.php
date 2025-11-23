<?php

namespace Yogeshgupta\PhonepeLaravel\Listeners;

use Yogeshgupta\PhonepeLaravel\Events\PhonePePaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandlePhonePePaymentFailed implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PhonePePaymentFailed $event)
    {
        // Example: Find order by merchant_order_id and mark as paid
        // $order = Order::where('merchant_order_id', $event->log->merchant_order_id)->first();
        // if ($order) { $order->update(['status' => 'paid', 'paid_at' => now()]); }

        // ← Users will override this after publishing
    }
}