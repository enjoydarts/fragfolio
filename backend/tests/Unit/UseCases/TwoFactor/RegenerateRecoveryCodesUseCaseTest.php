<?php

use App\Models\User;
use App\UseCases\TwoFactor\RegenerateRecoveryCodesUseCase;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

describe('RegenerateRecoveryCodesUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->generateNewRecoveryCodes = \Mockery::mock(GenerateNewRecoveryCodes::class);
        $this->useCase = new RegenerateRecoveryCodesUseCase($this->generateNewRecoveryCodes);
        createDefaultRoles();
    });

    test('リカバリーコードを再生成できる', function () {
        $oldCodes = ['old1', 'old2', 'old3'];
        $newCodes = ['new1', 'new2', 'new3', 'new4', 'new5', 'new6', 'new7', 'new8'];

        $this->user->forceFill([
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($oldCodes)),
        ])->save();

        // Fortifyアクションが成功することをモック
        $this->generateNewRecoveryCodes->shouldReceive('__invoke')
            ->once()
            ->with($this->user)
            ->andReturnUsing(function ($user) use ($newCodes) {
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode($newCodes)),
                ])->save();
            });

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeArray();
        expect(count($result))->toBe(8);
        expect($result)->toEqual($newCodes);

        // 新しいコードが生成されている
        expect($result)->not()->toContain('old1');
    });

    test('2段階認証が未確認の場合はエラー', function () {
        expect(fn () => $this->useCase->execute($this->user))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('生成されたリカバリーコードが正しい形式', function () {
        $newCodes = ['abc12345', 'def67890', 'ghi11111', 'jkl22222', 'mno33333', 'pqr44444', 'stu55555', 'vwx66666'];

        $this->user->forceFill([
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['old1'])),
        ])->save();

        $this->generateNewRecoveryCodes->shouldReceive('__invoke')
            ->once()
            ->with($this->user)
            ->andReturnUsing(function ($user) use ($newCodes) {
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode($newCodes)),
                ])->save();
            });

        $result = $this->useCase->execute($this->user);

        foreach ($result as $code) {
            // リカバリーコードは8文字の英数字
            expect(strlen($code))->toBe(8);
            expect(preg_match('/^[a-zA-Z0-9]+$/', $code))->toBe(1);
        }

        // ユニークなコードが生成されている
        $uniqueCodes = array_unique($result);
        expect(count($uniqueCodes))->toBe(count($result));
    });
});
