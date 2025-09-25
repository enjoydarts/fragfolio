<?php

use App\Models\User;
use App\UseCases\TwoFactor\EnableTwoFactorUseCase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;

describe('EnableTwoFactorUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->enableTwoFactorAction = \Mockery::mock(EnableTwoFactorAuthentication::class);
        $this->google2fa = new Google2FA;
        $this->useCase = new EnableTwoFactorUseCase($this->enableTwoFactorAction, $this->google2fa);
        createDefaultRoles();
    });

    test('2段階認証を有効化できる', function () {
        $testSecret = 'ABCDEFGHIJKLMNOP';

        // Fortifyアクションが成功することをモック
        $this->enableTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user)
            ->andReturnUsing(function ($user) use ($testSecret) {
                $user->forceFill([
                    'two_factor_secret' => encrypt($testSecret),
                    'two_factor_confirmed_at' => null,
                ])->save();
            });

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeArray();
        expect($result['secret'])->toBe($testSecret);
        expect($result['qr_code_url'])->toBeString();
        expect(strlen($result['secret']))->toBe(16);

        // ユーザーのシークレットが設定されている
        $this->user->refresh();
        expect($this->user->two_factor_secret)->not()->toBeNull();
        expect(decrypt($this->user->two_factor_secret))->toBe($result['secret']);

        // まだ確認されていない状態
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

    test('QRコードURLが正しく生成される', function () {
        $testSecret = 'ABCDEFGHIJKLMNOP';

        $this->enableTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user)
            ->andReturnUsing(function ($user) use ($testSecret) {
                $user->forceFill([
                    'two_factor_secret' => encrypt($testSecret),
                    'two_factor_confirmed_at' => null,
                ])->save();
            });

        $result = $this->useCase->execute($this->user);

        $expectedUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $this->user->email,
            $result['secret']
        );

        expect($result['qr_code_url'])->toBe($expectedUrl);
    });

    test('既に2段階認証が有効な場合はエラー', function () {
        // 既存のシークレットを設定
        $this->user->forceFill([
            'two_factor_secret' => encrypt('OLDSECRETNUMBER1'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        expect(fn () => $this->useCase->execute($this->user))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('生成されたシークレットは有効なGoogle2FAシークレット形式', function () {
        $testSecret = 'ABCDEFGHIJKLMNOP';

        $this->enableTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user)
            ->andReturnUsing(function ($user) use ($testSecret) {
                $user->forceFill([
                    'two_factor_secret' => encrypt($testSecret),
                    'two_factor_confirmed_at' => null,
                ])->save();
            });

        $result = $this->useCase->execute($this->user);

        // Base32エンコードされた文字列かチェック
        expect(preg_match('/^[A-Z2-7]+$/', $result['secret']))->toBe(1);

        // Google2FAで有効なコードを生成できるかテスト
        $otp = $this->google2fa->getCurrentOtp($result['secret']);
        expect($otp)->toBeString();
        expect(strlen($otp))->toBe(6);
        expect(is_numeric($otp))->toBe(true);
    });
});
