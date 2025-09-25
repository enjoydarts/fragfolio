<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

describe('TwoFactorAuthController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Auth::login($this->user);
        $this->google2fa = new Google2FA;
    });

    test('2段階認証の有効化ができる', function () {
        $response = $this->postJson('/api/auth/two-factor-authentication');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'secret',
                'qr_code_url',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('secret'))->toBeString();
        expect($response->json('qr_code_url'))->toContain('otpauth://totp/');

        // ユーザーにシークレットが保存されている
        $this->user->refresh();
        expect($this->user->two_factor_secret)->not()->toBeNull();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

    test('2段階認証の確認ができる', function () {
        // まず2段階認証を有効化
        $enableResponse = $this->postJson('/api/auth/two-factor-authentication');
        $secret = $enableResponse->json('secret');

        $validCode = $this->google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/confirmed-two-factor-authentication', [
            'code' => $validCode,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'recovery_codes',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('recovery_codes'))->toBeArray();
        expect(count($response->json('recovery_codes')))->toBe(8);

        // ユーザーが確認済み状態になっている
        $this->user->refresh();
        expect($this->user->two_factor_confirmed_at)->not()->toBeNull();
    });

    test('間違ったコードでは確認できない', function () {
        $this->postJson('/api/auth/two-factor-authentication');

        $response = $this->postJson('/api/auth/confirmed-two-factor-authentication', [
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('auth.two_factor_code_invalid'),
            ]);
    });

    test('2段階認証の無効化ができる', function () {
        // まず2段階認証を有効化
        $enableResponse = $this->postJson('/api/auth/two-factor-authentication');
        $secret = $enableResponse->json('secret');

        // 2段階認証を確認して有効状態にする
        $validCode = $this->google2fa->getCurrentOtp($secret);
        $this->postJson('/api/auth/confirmed-two-factor-authentication', [
            'code' => $validCode,
        ]);

        // 無効化のリクエスト
        $response = $this->deleteJson('/api/auth/two-factor-authentication');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => __('auth.two_factor_disabled'),
            ]);

        // ユーザーの2段階認証情報がクリアされている
        $this->user->refresh();
        expect($this->user->two_factor_secret)->toBeNull();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

    test('QRコードを取得できる', function () {
        // 2段階認証を有効化
        $this->postJson('/api/auth/two-factor-authentication');

        $response = $this->getJson('/api/auth/two-factor-qr-code');

        // QRコードはSVG形式で返される
        $response->assertStatus(200);
        expect($response->headers->get('Content-Type'))->toBe('image/svg+xml');
        expect($response->getContent())->toContain('<svg');
    });

    test('リカバリーコードを取得できる', function () {
        // まず2段階認証を有効化・確認してリカバリーコードを生成
        $enableResponse = $this->postJson('/api/auth/two-factor-authentication');
        $secret = $enableResponse->json('secret');

        $validCode = $this->google2fa->getCurrentOtp($secret);
        $confirmResponse = $this->postJson('/api/auth/confirmed-two-factor-authentication', [
            'code' => $validCode,
        ]);

        $expectedCodes = $confirmResponse->json('recovery_codes');

        $response = $this->getJson('/api/auth/two-factor-recovery-codes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'recovery_codes' => $expectedCodes,
            ]);
    });

    test('リカバリーコードを再生成できる', function () {
        // まず2段階認証を有効化・確認
        $enableResponse = $this->postJson('/api/auth/two-factor-authentication');
        $secret = $enableResponse->json('secret');

        $validCode = $this->google2fa->getCurrentOtp($secret);
        $confirmResponse = $this->postJson('/api/auth/confirmed-two-factor-authentication', [
            'code' => $validCode,
        ]);

        $oldCodes = $confirmResponse->json('recovery_codes');

        // リカバリーコードを再生成
        $response = $this->postJson('/api/auth/two-factor-recovery-codes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'recovery_codes',
                'message',
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('recovery_codes'))->toBeArray();
        expect(count($response->json('recovery_codes')))->toBe(8);

        // 新しいリカバリーコードが古いものと異なることを確認
        $newCodes = $response->json('recovery_codes');
        expect($newCodes)->not()->toBe($oldCodes);
    });

    test('未認証ユーザーはアクセスできない', function () {
        Auth::logout();

        $response = $this->postJson('/api/auth/two-factor-authentication');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/confirmed-two-factor-authentication', ['code' => '123456']);
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/auth/two-factor-authentication');
        $response->assertStatus(401);

        $response = $this->getJson('/api/auth/two-factor-qr-code');
        $response->assertStatus(401);

        $response = $this->getJson('/api/auth/two-factor-recovery-codes');
        $response->assertStatus(401);
    });
});
