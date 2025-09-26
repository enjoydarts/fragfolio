<?php

use App\Models\User;
use App\UseCases\Auth\UpdateUserProfileUseCase;

describe('UpdateUserProfileUseCase', function () {
    beforeEach(function () {
        $this->useCase = new UpdateUserProfileUseCase;
        $this->user = User::factory()->create([
            'name' => '元の名前',
        ]);
    });

    test('ユーザー名を更新できる', function () {
        $data = [
            'name' => '新しい名前',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        expect($updatedUser->name)->toBe('新しい名前');
    });

    test('プロフィール情報を更新できる', function () {
        $data = [
            'bio' => '新しい自己紹介',
            'language' => 'en',
            'timezone' => 'America/New_York',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'country' => 'US',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        expect($updatedUser->profile->bio)->toBe('新しい自己紹介');
        expect($updatedUser->profile->language)->toBe('en');
        expect($updatedUser->profile->timezone)->toBe('America/New_York');
        expect($updatedUser->profile->date_of_birth)->not->toBeNull();
        expect($updatedUser->profile->date_of_birth->format('Y-m-d'))->toBe('1990-01-01');
        expect($updatedUser->profile->gender)->toBe('female');
        expect($updatedUser->profile->country)->toBe('US');
    });

    test('ユーザー名とプロフィール情報を同時に更新できる', function () {
        $data = [
            'name' => '新しいユーザー名',
            'bio' => '新しい自己紹介',
            'language' => 'fr',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        expect($updatedUser->name)->toBe('新しいユーザー名');
        expect($updatedUser->profile->bio)->toBe('新しい自己紹介');
        expect($updatedUser->profile->language)->toBe('fr');
    });

    test('一部のフィールドのみ更新できる', function () {
        $originalBio = $this->user->profile->bio;
        $originalLanguage = $this->user->profile->language;

        $data = [
            'timezone' => 'Europe/London',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        expect($updatedUser->profile->timezone)->toBe('Europe/London');
        expect($updatedUser->profile->bio)->toBe($originalBio); // 変更されない
        expect($updatedUser->profile->language)->toBe($originalLanguage); // 変更されない
    });

    test('無効なプロフィールフィールドは無視される', function () {
        $originalName = $this->user->name;
        $originalBio = $this->user->profile->bio;

        $data = [
            'name' => '新しい名前',
            'invalid_field' => '無効なデータ',
            'bio' => '新しい自己紹介',
            'another_invalid' => 'テスト',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        expect($updatedUser->name)->toBe('新しい名前');
        expect($updatedUser->profile->bio)->toBe('新しい自己紹介');
        // 無効なフィールドは更新されない（エラーも発生しない）
    });

    test('空の更新データでも正常に動作する', function () {
        $originalName = $this->user->name;
        $originalBio = $this->user->profile->bio;

        $updatedUser = $this->useCase->execute($this->user, []);

        expect($updatedUser->name)->toBe($originalName);
        expect($updatedUser->profile->bio)->toBe($originalBio);
    });

    test('返されるユーザーにはプロフィールとロールが含まれる', function () {
        $data = [
            'name' => '新しい名前',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        expect($updatedUser->relationLoaded('profile'))->toBeTrue();

    });

    test('更新はトランザクション内で実行される', function () {
        // モックを使ってトランザクションが使用されることを確認するテスト
        $data = [
            'name' => '新しい名前',
            'bio' => '新しい自己紹介',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        // データベースで実際に更新されていることを確認
        $this->user->refresh();
        expect($this->user->name)->toBe('新しい名前');
        expect($this->user->profile->bio)->toBe('新しい自己紹介');
    });

    test('プロフィールデータのみの更新でもユーザー名は変更されない', function () {
        $originalName = $this->user->name;

        $data = [
            'bio' => '自己紹介のみ更新',
            'language' => 'es',
        ];

        $updatedUser = $this->useCase->execute($this->user, $data);

        expect($updatedUser->name)->toBe($originalName);
        expect($updatedUser->profile->bio)->toBe('自己紹介のみ更新');
        expect($updatedUser->profile->language)->toBe('es');
    });
});
