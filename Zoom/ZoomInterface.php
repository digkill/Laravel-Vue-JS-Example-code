<?php

namespace App\Services\Zoom;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

interface ZoomInterface
{
    const MEETING_TYPE_SCHEDULE = 2;
}
