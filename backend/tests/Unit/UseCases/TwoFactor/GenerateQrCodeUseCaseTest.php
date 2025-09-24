<?php

use App\Models\User;
use App\UseCases\TwoFactor\GenerateQrCodeUseCase;
use PragmaRX\Google2FA\Google2FA;

describe('GenerateQrCodeUseCase', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $this->useCase = new GenerateQrCodeUseCase();
        $this->google2fa = new Google2FA();
        createDefaultRoles();
    });

    test('QRコードSVGを生成できる', function () {
        $secret = 'ABCDEFGHIJKLMNOP';
        $this->user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeString();
        expect($result)->toContain('<svg');
        expect($result)->toContain('</svg>');
    });

    test('2段階認証が有効化されていない場合はエラー', function () {
        expect(fn () => $this->useCase->execute($this->user))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('確認済みの2段階認証ではエラー', function () {
        $secret = 'ABCDEFGHIJKLMNOP';
        $this->user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        expect(fn () => $this->useCase->execute($this->user))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('特殊文字を含むメールアドレスでも生成できる', function () {
        $secret = 'ABCDEFGHIJKLMNOP';
        $this->user->forceFill([
            'email' => 'test+special@example.com',
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeString();
        expect($result)->toContain('<svg');
    });

    test('日本語名のユーザーでも生成できる', function () {
        $secret = 'ABCDEFGHIJKLMNOP';
        $this->user->forceFill([
            'name' => 'テストユーザー',
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeString();
        expect($result)->toContain('<svg');
    });

    test('SVGコンテンツが正しい形式', function () {
        $secret = 'ABCDEFGHIJKLMNOP';
        $this->user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        $result = $this->useCase->execute($this->user);

        expect($result)->toBeString();
        expect($result)->toStartWith('<svg');
        expect($result)->toEndWith('</svg>');
    });
});