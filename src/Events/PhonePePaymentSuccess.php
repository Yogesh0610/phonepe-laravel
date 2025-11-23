<?php

namespace Yogeshgupta\PhonepeLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Yogeshgupta\PhonepeLaravel\Models\PhonePeLog;

class PhonePePaymentSuccess
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PhonePeLog $log
    ) {}
}