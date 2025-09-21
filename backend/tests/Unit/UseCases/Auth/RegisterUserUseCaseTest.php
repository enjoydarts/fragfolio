<?php

use App\Models\User;
use App\UseCases\Auth\RegisterUserUseCase;
use Illuminate\Support\Facades\Hash;

describe('RegisterUserUseCase', function () {
    beforeEach(function () {
        $this->useCase = new RegisterUserUseCase;
        createDefaultRoles();
    });

    test('新しいユーザーを正常に登録できる', function () {
        $data = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'language' => 'ja',
            'timezone' => 'Asia/Tokyo',
        ];

        $user = $this->useCase->execute($data);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->name)->toBe('テストユーザー');
        expect($user->email)->toBe('test@example.com');
        expect(Hash::check('password123', $user->password))->toBeTrue();
        expect($user->email_verified_at)->toBeNull();

        // プロフィールが作成されることを確認
        expect($user->profile)->not->toBeNull();
        expect($user->profile->language)->toBe('ja');
        expect($user->profile->timezone)->toBe('Asia/Tokyo');

        // ユーザーロールが割り当てられることを確認
        expect($user->hasRole('user'))->toBeTrue();
    });

    test('デフォルト値でユーザーを登録できる', function () {
        $data = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = $this->useCase->execute($data);

        expect($user->profile->language)->toBe('ja');
        expect($user->profile->timezone)->toBe('Asia/Tokyo');
    });

    test('登録されたユーザーにはプロフィールとロールが関連付けられる', function () {
        $data = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = $this->useCase->execute($data);

        // リレーションが正しく読み込まれることを確認
        expect($user->relationLoaded('profile'))->toBeTrue();
        expect($user->relationLoaded('roles'))->toBeTrue();
    });

    test('パスワードがハッシュ化されて保存される', function () {
        $plainPassword = 'password123';
        $data = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => $plainPassword,
        ];

        $user = $this->useCase->execute($data);

        expect($user->password)->not->toBe($plainPassword);
        expect(Hash::check($plainPassword, $user->password))->toBeTrue();
    });

    test('メールアドレスは認証待ち状態になる', function () {
        $data = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = $this->useCase->execute($data);

        expect($user->email_verified_at)->toBeNull();
        expect($user->hasVerifiedEmail())->toBeFalse();
    });
});
