<?php

namespace Yogeshgupta\PhonepeLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class PhonePeLog extends Model
{
    protected $guarded = [];
    protected $table = 'phone_pe_logs';

    protected $casts = [
        'raw_request' => 'array',
        'raw_response' => 'array',
        'webhook_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($log) {
            if (!$log->signature && $log->webhook_payload) {
                $log->signature = md5(json_encode($log->webhook_payload));
            }
        });
    }
}
