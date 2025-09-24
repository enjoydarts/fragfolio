<?php

use App\Models\User;
use App\UseCases\TwoFactor\VerifyTwoFactorLoginUseCase;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

describe('VerifyTwoFactorLoginUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->useCase = new VerifyTwoFactorLoginUseCase();
        $this->google2fa = new Google2FA();
        $this->secret = 'ABCDEFGHIJKLMNOP';
        createDefaultRoles();

        // 2段階認証を有効・確認済み状態にする
        $this->user->forceFill([
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // 一時トークンを設定
        $this->tempToken = 'temp-token-' . time();
        Cache::put("two_factor_pending:{$this->tempToken}", [
            'user_id' => $this->user->id,
            'remember' => false,
            'login_method' => 'password',
        ], now()->addMinutes(10));
    });

    test('正しいTOTPコードで2段階認証ログインができる', function () {
        $validCode = $this->google2fa->getCurrentOtp($this->secret);

        $result = $this->useCase->execute($validCode, $this->tempToken);

        expect($result)->toBeArray();
        expect($result['user'])->not()->toBeNull();
        expect($result['token'])->toBeString();

        // キャッシュがクリアされている
        expect(Cache::has("two_factor_pending:{$this->tempToken}"))->toBe(false);
    });

    test('正しいリカバリーコードで2段階認証ログインができる', function () {
        $recoveryCodes = ['recovery1', 'recovery2', 'recovery3'];
        $this->user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        $result = $this->useCase->execute('recovery1', $this->tempToken);

        expect($result)->toBeArray();
        expect($result['user'])->not()->toBeNull();
        expect($result['token'])->toBeString();

        // リカバリーコードが使用済みになっている
        $this->user->refresh();
        $updatedCodes = json_decode(decrypt($this->user->two_factor_recovery_codes), true);
        expect($updatedCodes)->not()->toContain('recovery1');
        expect($updatedCodes)->toContain('recovery2');
    });

    test('間違ったTOTPコードでは認証失敗', function () {
        expect(fn () => $this->useCase->execute('000000', $this->tempToken))
            ->toThrow(\InvalidArgumentException::class);

        // キャッシュは残っている
        expect(Cache::has("two_factor_pending:{$this->tempToken}"))->toBe(true);
    });

    test('存在しない一時トークンでエラー', function () {
        expect(fn () => $this->useCase->execute('123456', 'invalid-token'))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('期限切れの一時トークンでエラー', function () {
        Cache::forget("two_factor_pending:{$this->tempToken}");

        $validCode = $this->google2fa->getCurrentOtp($this->secret);
        expect(fn () => $this->useCase->execute($validCode, $this->tempToken))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('remember機能が動作する', function () {
        // remember=trueの一時トークン
        Cache::put("two_factor_pending:{$this->tempToken}", [
            'user_id' => $this->user->id,
            'remember' => true,
            'login_method' => 'password',
        ], now()->addMinutes(10));

        $validCode = $this->google2fa->getCurrentOtp($this->secret);
        $result = $this->useCase->execute($validCode, $this->tempToken);

        expect($result['user'])->not()->toBeNull();
        expect($result['token'])->toBeString();
    });


    test('無効なリカバリーコードでエラー', function () {
        $recoveryCodes = ['recovery1', 'recovery2'];
        $this->user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        expect(fn () => $this->useCase->execute('invalid-recovery', $this->tempToken))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('WebAuthnログイン方式でも動作する', function () {
        // WebAuthnログイン方式の一時トークン
        Cache::put("two_factor_pending:{$this->tempToken}", [
            'user_id' => $this->user->id,
            'remember' => false,
            'login_method' => 'webauthn',
        ], now()->addMinutes(10));

        $validCode = $this->google2fa->getCurrentOtp($this->secret);
        $result = $this->useCase->execute($validCode, $this->tempToken);

        expect($result['user'])->not()->toBeNull();
        expect($result['token'])->toBeString();
    });
});