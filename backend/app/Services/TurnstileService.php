<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    private string $secretKey;

    private string $siteKey;

    public function __construct()
    {
        $this->secretKey = config('services.turnstile.secret_key') ?? '';
        $this->siteKey = config('services.turnstile.site_key') ?? '';
    }

    public function verify(string $token, ?string $remoteIp = null): bool|string
    {
        if (! $this->secretKey) {
            Log::warning('Turnstile secret key is not configured');

            return true; // Fail open in development
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);

            if (! $response->successful()) {
                Log::error('Turnstile verification request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json();

            if (! isset($data['success'])) {
                Log::error('Invalid Turnstile response format', ['response' => $data]);

                return false;
            }

            if (! $data['success']) {
                $errorCodes = $data['error-codes'] ?? [];

                Log::warning('Turnstile verification failed', [
                    'error_codes' => $errorCodes,
                    'token' => substr($token, 0, 10).'...',
                ]);

                // 特定のエラーコードを返す
                if (in_array('timeout-or-duplicate', $errorCodes)) {
                    return 'timeout_or_duplicate';
                }
                if (in_array('invalid-input-response', $errorCodes)) {
                    return 'invalid_input_response';
                }
                if (in_array('missing-input-response', $errorCodes)) {
                    return 'missing_input_response';
                }

                return 'verification_failed';
            }

            Log::info('Turnstile verification successful', [
                'token' => substr($token, 0, 10).'...',
                'challenge_ts' => $data['challenge_ts'] ?? null,
                'hostname' => $data['hostname'] ?? null,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Turnstile verification exception', [
                'message' => $e->getMessage(),
                'token' => substr($token, 0, 10).'...',
            ]);

            return false;
        }
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secretKey) && ! empty($this->siteKey);
    }
}
