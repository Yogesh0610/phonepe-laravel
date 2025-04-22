<?php

namespace YogeshGupta\PhonePe\Facades;

use Illuminate\Support\Facades\Facade;

class PhonePe extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'phonepe';
    }
}
?>