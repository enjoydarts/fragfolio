<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

describe('TOTP 2段階認証管理', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Auth::login($this->user);
        $this->google2fa = new Google2FA;
    });

    test('2段階認証を有効化できる', function () {
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
        expect($response->json('qr_code_url'))->toBeString();

        // ユーザーのシークレットが設定されているが、まだ確認されていない
        $this->user->refresh();
        expect($this->user->two_factor_secret)->not()->toBeNull();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

    test('2段階認証の有効化を確認できる', function () {
        // まず2段階認証を有効化
        $enableResponse = $this->postJson('/api/auth/two-factor-authentication');
        $secret = $enableResponse->json('secret');

        // 確認用のコードを生成
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

        // ユーザーの2段階認証が確認済みになっている
        $this->user->refresh();
        expect($this->user->two_factor_confirmed_at)->not()->toBeNull();
        expect($this->user->two_factor_recovery_codes)->not()->toBeNull();
    });

    test('間違ったコードでは2段階認証の確認ができない', function () {
        // まず2段階認証を有効化
        $this->postJson('/api/auth/two-factor-authentication');

        $response = $this->postJson('/api/auth/confirmed-two-factor-authentication', [
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('auth.two_factor_code_invalid'),
            ]);

        // ユーザーの2段階認証が確認されていない
        $this->user->refresh();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

    test('2段階認証を無効化できる', function () {
        // まず2段階認証を有効化・確認
        $this->user->two_factor_secret = encrypt('ABCDEFGHIJKLMNOP');
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();
        $this->user->refresh();

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
        expect($this->user->two_factor_recovery_codes)->toBeNull();
    });

    test('リカバリーコードを取得できる', function () {
        // 2段階認証が有効化・確認済みの状態にする
        $recoveryCodes = ['code1', 'code2', 'code3'];
        $this->user->two_factor_secret = encrypt('ABCDEFGHIJKLMNOP');
        $this->user->two_factor_confirmed_at = now();
        $this->user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $this->user->save();
        $this->user->refresh();

        $response = $this->getJson('/api/auth/two-factor-recovery-codes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'recovery_codes' => $recoveryCodes,
            ]);
    });

    test('リカバリーコードを再生成できる', function () {
        // 2段階認証が有効化・確認済みの状態にする
        $this->user->two_factor_secret = encrypt('ABCDEFGHIJKLMNOP');
        $this->user->two_factor_confirmed_at = now();
        $this->user->two_factor_recovery_codes = encrypt(json_encode(['old-code']));
        $this->user->save();
        $this->user->refresh();

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

        // 新しいリカバリーコードが保存されている
        $this->user->refresh();
        $newCodes = json_decode(decrypt($this->user->two_factor_recovery_codes), true);
        expect($newCodes)->not()->toContain('old-code');
    });

    test('未認証ユーザーは2段階認証操作ができない', function () {
        Auth::logout();

        $response = $this->postJson('/api/auth/two-factor-authentication');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/confirmed-two-factor-authentication', ['code' => '123456']);
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/two-factor-authentication');
        $response->assertStatus(401);

        $response = $this->getJson('/api/auth/two-factor-recovery-codes');
        $response->assertStatus(401);
    });

    test('2段階認証が無効な状態でQRコード取得はできない', function () {
        $response = $this->getJson('/api/auth/two-factor-qr-code');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => __('auth.two_factor_not_enabled'),
            ]);
    });

    test('2段階認証有効化後はQRコードを取得できる', function () {
        // 2段階認証を有効化
        $this->postJson('/api/auth/two-factor-authentication');

        $response = $this->get('/api/auth/two-factor-qr-code');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/svg+xml');
    });
});
