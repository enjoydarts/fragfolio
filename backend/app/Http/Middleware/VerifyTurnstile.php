<?php

namespace App\Http\Middleware;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(
        private TurnstileService $turnstileService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 開発環境でTurnstileが設定されていない場合はスキップ
        if (! $this->turnstileService->isConfigured()) {
            return $next($request);
        }

        $token = $request->input('turnstile_token') ?? $request->header('X-Turnstile-Token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Turnstileトークンが必要です',
            ], 422);
        }

        $remoteIp = $request->ip();

        if (! $this->turnstileService->verify($token, $remoteIp)) {
            return response()->json([
                'success' => false,
                'message' => 'Turnstile検証に失敗しました',
            ], 422);
        }

        return $next($request);
    }
}
