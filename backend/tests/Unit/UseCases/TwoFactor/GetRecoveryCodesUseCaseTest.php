<?php

use App\Models\User;
use App\UseCases\TwoFactor\GetRecoveryCodesUseCase;

describe('GetRecoveryCodesUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->useCase = new GetRecoveryCodesUseCase;
        createDefaultRoles();
    });

    test('リカバリーコードを取得できる', function () {
        $recoveryCodes = ['code1', 'code2', 'code3'];
        $this->user->forceFill([
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeArray();
        expect($result)->toEqual($recoveryCodes);
    });

    test('2段階認証が未確認の場合はエラー', function () {
        expect(fn () => $this->useCase->execute($this->user))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('リカバリーコードが設定されていない場合はエラー', function () {
        $this->user->forceFill([
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => null,
        ])->save();

        expect(fn () => $this->useCase->execute($this->user))
            ->toThrow(\InvalidArgumentException::class);
    });

});
