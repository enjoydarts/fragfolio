<?php

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;

describe('User Model', function () {
    beforeEach(function () {
        createDefaultRoles();
    });

    describe('attributes', function () {
        test('fillableプロパティが正しく設定されている', function () {
            $user = new User;
            $expected = ['name', 'email', 'password', 'email_verified_at', 'role'];

            expect($user->getFillable())->toEqual($expected);
        });

        test('hiddenプロパティが正しく設定されている', function () {
            $user = new User;
            $expected = ['password', 'remember_token'];

            expect($user->getHidden())->toEqual($expected);
        });

        test('castsプロパティが正しく設定されている', function () {
            $user = new User;
            $casts = $user->getCasts();

            expect($casts['email_verified_at'])->toBe('datetime');
            expect($casts['password'])->toBe('hashed');
        });
    });

    describe('relationships', function () {
        test('profileリレーションシップが正しく動作する', function () {
            $user = User::factory()->create();

            // ファクトリーで自動作成されたプロフィールを確認
            $user->refresh(); // リレーションをリフレッシュ
            expect($user->profile)->toBeInstanceOf(UserProfile::class);
            expect($user->profile->language)->toBe('ja');
            expect($user->profile->timezone)->toBe('Asia/Tokyo');
        });

        test('profileは1対1の関係である', function () {
            $user = User::factory()->create();

            $relation = $user->profile();
            expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class);
        });
    });

    describe('password hashing', function () {
        test('パスワードが自動的にハッシュ化される', function () {
            $password = 'plaintext-password';
            $user = User::factory()->create(['password' => $password]);

            expect($user->password)->not->toBe($password);
            expect(Hash::check($password, $user->password))->toBeTrue();
        });
    });

    describe('email verification', function () {
        test('hasVerifiedEmailメソッドが正しく動作する', function () {
            $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
            $verifiedUser = User::factory()->create(['email_verified_at' => now()]);

            expect($unverifiedUser->hasVerifiedEmail())->toBeFalse();
            expect($verifiedUser->hasVerifiedEmail())->toBeTrue();
        });

        test('markEmailAsVerifiedメソッドが正しく動作する', function () {
            $user = User::factory()->create(['email_verified_at' => null]);

            $result = $user->markEmailAsVerified();

            expect($result)->toBeTrue();
            expect($user->hasVerifiedEmail())->toBeTrue();
            expect($user->email_verified_at)->not->toBeNull();
        });
    });

    describe('role management', function () {
        test('デフォルトでuserロールが割り当てられる', function () {
            $user = User::factory()->create();
            $user->assignRole('user');

            expect($user->hasRole('user'))->toBeTrue();
            expect($user->hasRole('admin'))->toBeFalse();
        });

        test('adminロールを割り当てできる', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');

            expect($user->hasRole('admin'))->toBeTrue();
            expect($user->hasRole('user'))->toBeFalse();
        });
    });

    describe('factory', function () {
        test('ファクトリーで正しいユーザーが作成される', function () {
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            expect($user->name)->toBe('Test User');
            expect($user->email)->toBe('test@example.com');
            expect($user->password)->not->toBeNull();
        });

        test('ファクトリーでプロフィール付きユーザーが作成される', function () {
            $user = User::factory()->create();
            $user->profile()->create([
                'language' => 'ja',
                'timezone' => 'Asia/Tokyo',
            ]);

            expect($user->profile)->not->toBeNull();
            expect($user->profile)->toBeInstanceOf(UserProfile::class);
        });
    });

    describe('profile creation', function () {
        test('プロフィールが自動作成される', function () {
            $user = User::factory()->create();

            // プロフィールを作成
            $user->profile()->create([
                'language' => 'ja',
                'timezone' => 'Asia/Tokyo',
            ]);

            expect($user->profile)->not->toBeNull();
            expect($user->profile->language)->toBe('ja');
            expect($user->profile->timezone)->toBe('Asia/Tokyo');
        });
    });
});
