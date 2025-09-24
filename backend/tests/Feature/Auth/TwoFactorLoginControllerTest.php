<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

describe('TwoFactorLoginController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->google2fa = new Google2FA();

        // 一時トークンを設定
        $this->tempToken = 'temp-token-' . time();
        Cache::put("two_factor_pending:{$this->tempToken}", [
            'user_id' => $this->user->id,
            'remember' => false,
            'login_method' => 'password',
        ], now()->addMinutes(10));
    });

    test('正しいTOTPコードで2段階認証ログインができる', function () {
        $validCode = $this->google2fa->getCurrentOtp(decrypt($this->user->two_factor_secret));

        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
            'code' => $validCode,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'two_factor_enabled',
                ],
                'token',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('user.id'))->toBe($this->user->id);
        expect($response->json('token'))->toBeString();

        // 一時トークンがキャッシュから削除されている
        expect(Cache::has("two_factor_pending:{$this->tempToken}"))->toBe(false);
    });

    test('正しいリカバリーコードで2段階認証ログインができる', function () {
        $recoveryCodes = ['recovery1', 'recovery2', 'recovery3'];
        $this->user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $this->user->save();
        $this->user->refresh();

        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
            'code' => 'recovery1',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user',
                'token',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);

        // リカバリーコードが使用済みになっている
        $this->user->refresh();
        $updatedCodes = json_decode(decrypt($this->user->two_factor_recovery_codes), true);
        expect($updatedCodes)->not()->toContain('recovery1');
        expect($updatedCodes)->toContain('recovery2');
    });

    test('間違ったTOTPコードでは2段階認証ログインできない', function () {
        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('auth.two_factor_failed'),
            ]);

        // 一時トークンがキャッシュに残っている
        expect(Cache::has("two_factor_pending:{$this->tempToken}"))->toBe(true);
    });

    test('存在しない一時トークンでは2段階認証ログインできない', function () {
        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => 'invalid-token',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('auth.two_factor_token_expired'),
            ]);
    });

    test('期限切れの一時トークンでは2段階認証ログインできない', function () {
        Cache::forget("two_factor_pending:{$this->tempToken}");

        $validCode = $this->google2fa->getCurrentOtp(decrypt($this->user->two_factor_secret));

        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
            'code' => $validCode,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('auth.two_factor_token_expired'),
            ]);
    });

    test('間違ったリカバリーコードでは2段階認証ログインできない', function () {
        $recoveryCodes = ['recovery1', 'recovery2'];
        $this->user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $this->user->save();
        $this->user->refresh();

        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
            'code' => 'wrong-recovery-code',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('auth.two_factor_failed'),
            ]);
    });

    test('コードもリカバリーコードも指定されていない場合はエラー', function () {
        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    });

    test('remember機能付きで2段階認証ログインができる', function () {
        // remember=trueの一時トークンを作成
        Cache::put("two_factor_pending:{$this->tempToken}", [
            'user_id' => $this->user->id,
            'remember' => true,
            'login_method' => 'password',
        ], now()->addMinutes(10));

        $validCode = $this->google2fa->getCurrentOtp(decrypt($this->user->two_factor_secret));

        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
            'code' => $validCode,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user',
                'token',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
    });

    test('WebAuthn方式の一時トークンでも動作する', function () {
        Cache::put("two_factor_pending:{$this->tempToken}", [
            'user_id' => $this->user->id,
            'remember' => false,
            'login_method' => 'webauthn',
        ], now()->addMinutes(10));

        $validCode = $this->google2fa->getCurrentOtp(decrypt($this->user->two_factor_secret));

        $response = $this->postJson('/api/auth/two-factor-challenge', [
            'temp_token' => $this->tempToken,
            'code' => $validCode,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    });

    test('バリデーションエラーのテスト', function () {
        $response = $this->postJson('/api/auth/two-factor-challenge', [
            // temp_tokenなし
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['temp_token']);
    });
});