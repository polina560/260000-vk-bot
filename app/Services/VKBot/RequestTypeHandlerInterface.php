<?php

namespace App\Services\VKBot;

use Illuminate\Http\Request;

interface RequestTypeHandlerInterface
{
    public static function handle(Request $request);
}
