<?php

use App\Http\Middleware\VerifyTurnstile;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

describe('VerifyTurnstile', function () {
    beforeEach(function () {
        Mockery::close();
        $this->turnstileService = Mockery::mock(TurnstileService::class);
        $this->middleware = new VerifyTurnstile($this->turnstileService);
        $this->request = Request::create('/test', 'POST');
        $this->next = function ($request) {
            return response('Success');
        };
    });

    afterEach(function () {
        Mockery::close();
    });

    test('Turnstile設定がない場合はスキップされる', function () {
        config(['services.turnstile.site_key' => null]);

        $response = $this->middleware->handle($this->request, $this->next);

        expect($response->getContent())->toBe('Success');
    });

    test('cf-turnstile-responseがない場合はエラー', function () {
        config(['services.turnstile.site_key' => 'test-site-key']);

        expect(fn() => $this->middleware->handle($this->request, $this->next))
            ->toThrow(ValidationException::class);

        try {
            $this->middleware->handle($this->request, $this->next);
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('cf-turnstile-response');
            expect($e->errors()['cf-turnstile-response'][0])->toBe(__('turnstile.missing_input_response'));
        }
    });

    test('Turnstile検証が成功した場合はリクエストが続行される', function () {
        config(['services.turnstile.site_key' => 'test-site-key']);
        $this->request->merge(['cf-turnstile-response' => 'valid-token']);

        $this->turnstileService
            ->shouldReceive('verify')
            ->with('valid-token', $this->request->ip())
            ->once()
            ->andReturn(true);

        $response = $this->middleware->handle($this->request, $this->next);

        expect($response->getContent())->toBe('Success');
    });

    test('Turnstile検証が失敗した場合はエラー', function () {
        config(['services.turnstile.site_key' => 'test-site-key']);
        $this->request->merge(['cf-turnstile-response' => 'invalid-token']);

        $this->turnstileService
            ->shouldReceive('verify')
            ->with('invalid-token', $this->request->ip())
            ->once()
            ->andReturn(false);

        try {
            $this->middleware->handle($this->request, $this->next);
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('cf-turnstile-response');
            expect($e->errors()['cf-turnstile-response'][0])->toBe(__('turnstile.verification_failed'));
            return;
        }

        $this->fail('Expected ValidationException was not thrown');
    });

    test('Turnstile検証でエラーコードが返された場合は適切なメッセージが表示される', function () {
        config(['services.turnstile.site_key' => 'test-site-key']);
        $this->request->merge(['cf-turnstile-response' => 'expired-token']);

        $this->turnstileService
            ->shouldReceive('verify')
            ->with('expired-token', $this->request->ip())
            ->once()
            ->andReturn('timeout_or_duplicate');

        try {
            $this->middleware->handle($this->request, $this->next);
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('cf-turnstile-response');
            expect($e->errors()['cf-turnstile-response'][0])->toBe(__('turnstile.timeout_or_duplicate'));
            return;
        }

        $this->fail('Expected ValidationException was not thrown');
    });

    test('TurnstileServiceに正しいパラメータが渡される', function () {
        config(['services.turnstile.site_key' => 'test-site-key']);
        $this->request->merge(['cf-turnstile-response' => 'test-token']);
        $this->request->server->set('REMOTE_ADDR', '192.168.1.1');

        $this->turnstileService
            ->shouldReceive('verify')
            ->with('test-token', '192.168.1.1')
            ->once()
            ->andReturn(true);

        $this->middleware->handle($this->request, $this->next);
    });

    test('リクエストIPが正しく取得される', function () {
        config(['services.turnstile.site_key' => 'test-site-key']);
        $this->request->merge(['cf-turnstile-response' => 'test-token']);

        // X-Forwarded-Forヘッダーを設定
        $this->request->headers->set('X-Forwarded-For', '203.0.113.1, 192.168.1.1');

        $this->turnstileService
            ->shouldReceive('verify')
            ->with('test-token', $this->request->ip())
            ->once()
            ->andReturn(true);

        $this->middleware->handle($this->request, $this->next);
    });

    test('レスポンスがそのまま返される', function () {
        config(['services.turnstile.site_key' => 'test-site-key']);
        $this->request->merge(['cf-turnstile-response' => 'valid-token']);

        $this->turnstileService
            ->shouldReceive('verify')
            ->andReturn(true);

        $customNext = function ($request) {
            return response()->json(['status' => 'custom'], 201);
        };

        $response = $this->middleware->handle($this->request, $customNext);

        expect($response->getStatusCode())->toBe(201);
        expect($response->getData(true))->toBe(['status' => 'custom']);
    });
});