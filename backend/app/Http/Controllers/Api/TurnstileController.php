<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TurnstileService;
use Illuminate\Http\JsonResponse;

class TurnstileController extends Controller
{
    public function __construct(
        private TurnstileService $turnstileService
    ) {}

    public function config(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->turnstileService->isConfigured(),
            'site_key' => $this->turnstileService->getSiteKey(),
        ]);
    }
}
