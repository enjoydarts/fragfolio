<?php

use App\Models\User;
use App\UseCases\TwoFactor\DisableTwoFactorUseCase;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

describe('DisableTwoFactorUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->disableTwoFactorAction = \Mockery::mock(DisableTwoFactorAuthentication::class);
        $this->useCase = new DisableTwoFactorUseCase($this->disableTwoFactorAction);
        createDefaultRoles();
    });

    test('2段階認証を無効化できる', function () {
        // 2段階認証が有効な状態にする
        $this->user->forceFill([
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        ])->save();

        // Fortifyアクションが成功することをモック
        $this->disableTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user)
            ->andReturnUsing(function ($user) {
                $user->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'two_factor_recovery_codes' => null,
                ])->save();
            });

        $this->useCase->execute($this->user);

        // ユーザーの2段階認証情報がクリアされている
        $this->user->refresh();
        expect($this->user->two_factor_secret)->toBeNull();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
        expect($this->user->two_factor_recovery_codes)->toBeNull();
    });

    test('2段階認証が無効な場合はエラー', function () {
        expect(fn () => $this->useCase->execute($this->user))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('未確認の2段階認証も無効化できる', function () {
        // 2段階認証が設定されているが確認されていない状態
        $this->user->forceFill([
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => null,
        ])->save();

        // Fortifyアクションが成功することをモック
        $this->disableTwoFactorAction->shouldReceive('__invoke')
            ->once()
            ->with($this->user)
            ->andReturnUsing(function ($user) {
                $user->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'two_factor_recovery_codes' => null,
                ])->save();
            });

        $this->useCase->execute($this->user);

        // シークレットがクリアされている
        $this->user->refresh();
        expect($this->user->two_factor_secret)->toBeNull();
    });
});
