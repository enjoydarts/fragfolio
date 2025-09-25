<?php

use App\Services\TurnstileService;
use Illuminate\Support\Facades\Http;

describe('TurnstileService', function () {
    beforeEach(function () {
        config([
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);

        $this->service = new TurnstileService;
    });

    describe('isConfigured', function () {
        test('サイトキーとシークレットキーが設定されている場合はtrueを返す', function () {
            expect($this->service->isConfigured())->toBeTrue();
        });

        test('サイトキーが未設定の場合はfalseを返す', function () {
            config(['services.turnstile.site_key' => '']);
            $service = new TurnstileService;

            expect($service->isConfigured())->toBeFalse();
        });

        test('シークレットキーが未設定の場合はfalseを返す', function () {
            config(['services.turnstile.secret_key' => '']);
            $service = new TurnstileService;

            expect($service->isConfigured())->toBeFalse();
        });
    });

    describe('getSiteKey', function () {
        test('設定されたサイトキーを返す', function () {
            expect($this->service->getSiteKey())->toBe('test-site-key');
        });
    });

    describe('verify', function () {
        test('シークレットキーが未設定の場合はtrueを返す（開発環境用）', function () {
            config(['services.turnstile.secret_key' => '']);
            $service = new TurnstileService;

            $result = $service->verify('test-token');

            expect($result)->toBeTrue();
        });

        test('正常なレスポンスでトークン検証が成功する', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => true,
                    'challenge_ts' => '2023-01-01T00:00:00.000Z',
                    'hostname' => 'example.com',
                ]),
            ]);

            $result = $this->service->verify('valid-token', '127.0.0.1');

            expect($result)->toBeTrue();

            Http::assertSent(function ($request) {
                return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify' &&
                       $request['secret'] === 'test-secret-key' &&
                       $request['response'] === 'valid-token' &&
                       $request['remoteip'] === '127.0.0.1';
            });
        });

        test('Turnstileサーバーがtimeout_or_duplicateエラーを返した場合', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => false,
                    'error-codes' => ['timeout-or-duplicate'],
                ]),
            ]);

            $result = $this->service->verify('expired-token');

            expect($result)->toBe('timeout_or_duplicate');
        });

        test('Turnstileサーバーがinvalid_input_responseエラーを返した場合', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => false,
                    'error-codes' => ['invalid-input-response'],
                ]),
            ]);

            $result = $this->service->verify('invalid-token');

            expect($result)->toBe('invalid_input_response');
        });

        test('Turnstileサーバーがmissing_input_responseエラーを返した場合', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => false,
                    'error-codes' => ['missing-input-response'],
                ]),
            ]);

            $result = $this->service->verify('missing-token');

            expect($result)->toBe('missing_input_response');
        });

        test('Turnstileサーバーが未知のエラーを返した場合', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => false,
                    'error-codes' => ['unknown-error'],
                ]),
            ]);

            $result = $this->service->verify('invalid-token');

            expect($result)->toBe('verification_failed');
        });

        test('Turnstileサーバーが失敗レスポンスを返した場合はfalseを返す', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => false,
                    'error-codes' => ['invalid-input-response'],
                ]),
            ]);

            $result = $this->service->verify('invalid-token');

            expect($result)->toBe('invalid_input_response');
        });

        test('HTTPリクエストが失敗した場合はfalseを返す', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response('Server Error', 500),
            ]);

            $result = $this->service->verify('test-token');

            expect($result)->toBeFalse();
        });

        test('レスポンスが無効な形式の場合はfalseを返す', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'invalid' => 'response',
                ]),
            ]);

            $result = $this->service->verify('test-token');

            expect($result)->toBeFalse();
        });

        test('HTTPリクエストで例外が発生した場合はfalseを返す', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => function () {
                    throw new Exception('Network error');
                },
            ]);

            $result = $this->service->verify('test-token');

            expect($result)->toBeFalse();
        });

        test('リモートIPアドレスなしでも検証できる', function () {
            Http::fake([
                'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                    'success' => true,
                ]),
            ]);

            $result = $this->service->verify('valid-token');

            expect($result)->toBeTrue();

            Http::assertSent(function ($request) {
                return $request['remoteip'] === null;
            });
        });
    });
});
