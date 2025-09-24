<?php

use App\Models\User;
use App\UseCases\TwoFactor\ConfirmTwoFactorUseCase;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Validation\ValidationException;

describe('ConfirmTwoFactorUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->confirmTwoFactorAction = \Mockery::mock(ConfirmTwoFactorAuthentication::class);
        $this->useCase = new ConfirmTwoFactorUseCase($this->confirmTwoFactorAction);
        $this->google2fa = new Google2FA();
        $this->secret = 'ABCDEFGHIJKLMNOP';
        createDefaultRoles();
    });

    test('正しいコードで2段階認証を確認できる', function () {
        // シークレットを設定（まだ確認されていない状態）
        $this->user->forceFill([
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $validCode = $this->google2fa->getCurrentOtp($this->secret);

        // Fortifyアクションが成功することをモック
        $this->confirmTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user, $validCode)
            ->andReturnUsing(function($user, $code) {
                // Fortifyの動作をシミュレート
                $user->forceFill([
                    'two_factor_confirmed_at' => now(),
                    'two_factor_recovery_codes' => encrypt(json_encode([
                        'abc12345', 'def67890', 'ghi11111', 'jkl22222',
                        'mno33333', 'pqr44444', 'stu55555', 'vwx66666'
                    ]))
                ])->save();
            });

        $result = $this->useCase->execute($this->user, $validCode);

        expect($result)->toBeArray();
        expect($result['recovery_codes'])->toBeArray();
        expect(count($result['recovery_codes']))->toBe(8);

        // ユーザーの確認状態が更新されている
        $this->user->refresh();
        expect($this->user->two_factor_confirmed_at)->not()->toBeNull();
        expect($this->user->two_factor_recovery_codes)->not()->toBeNull();
    });

    test('間違ったコードでは2段階認証を確認できない', function () {
        $this->user->forceFill([
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        // FortifyアクションがValidationExceptionを投げることをモック
        $this->confirmTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user, '000000')
            ->andThrow(ValidationException::withMessages(['code' => ['無効なコードです']]));

        expect(fn () => $this->useCase->execute($this->user, '000000'))
            ->toThrow(ValidationException::class);

        // ユーザーの状態は変更されていない
        $this->user->refresh();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

    test('2段階認証が設定されていない場合はエラー', function () {
        expect(fn () => $this->useCase->execute($this->user, '123456'))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('既に確認済みの場合はエラー', function () {
        $this->user->forceFill([
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $validCode = $this->google2fa->getCurrentOtp($this->secret);

        expect(fn () => $this->useCase->execute($this->user, $validCode))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('生成されたリカバリーコードが正しい形式', function () {
        $this->user->forceFill([
            'two_factor_secret' => encrypt($this->secret),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $validCode = $this->google2fa->getCurrentOtp($this->secret);

        // Fortifyアクションが成功することをモック
        $this->confirmTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user, $validCode)
            ->andReturnUsing(function($user, $code) {
                $user->forceFill([
                    'two_factor_confirmed_at' => now(),
                    'two_factor_recovery_codes' => encrypt(json_encode([
                        'abc12345', 'def67890', 'ghi11111', 'jkl22222',
                        'mno33333', 'pqr44444', 'stu55555', 'vwx66666'
                    ]))
                ])->save();
            });

        $result = $this->useCase->execute($this->user, $validCode);

        foreach ($result['recovery_codes'] as $code) {
            // リカバリーコードは8文字の英数字
            expect(strlen($code))->toBe(8);
            expect(preg_match('/^[a-zA-Z0-9]+$/', $code))->toBe(1);
        }
    });
});