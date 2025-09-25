<?php

namespace App\Http\Middleware;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstile
{
    public function __construct(
        private TurnstileService $turnstileService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Turnstile設定がない場合はスキップ
        if (! config('services.turnstile.site_key')) {
            return $next($request);
        }

        // cf-turnstile-responseがない場合はエラー
        if (! $request->has('cf-turnstile-response')) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => [__('turnstile.missing_input_response')],
            ]);
        }

        // Turnstile検証
        $verificationResult = $this->turnstileService->verify(
            $request->input('cf-turnstile-response'),
            $request->ip()
        );

        if ($verificationResult !== true) {
            $errorKey = is_string($verificationResult) ? $verificationResult : 'verification_failed';
            throw ValidationException::withMessages([
                'cf-turnstile-response' => [__("turnstile.{$errorKey}")],
            ]);
        }

        return $next($request);
    }
}
