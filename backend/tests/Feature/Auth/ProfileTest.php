<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('プロフィール管理', function () {
    test('認証されたユーザーは自分の情報を取得できる', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'profile' => [
                        'language',
                        'timezone',
                        'bio',
                        'date_of_birth',
                        'gender',
                        'country',
                    ],

                ],
            ])
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    });

    test('認証されたユーザーはプロフィールを更新できる', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $updateData = [
            'name' => '更新されたユーザー名',
            'bio' => '新しい自己紹介文',
            'language' => 'en',
            'timezone' => 'America/New_York',
            'gender' => 'male',
            'country' => 'US',
        ];

        $response = $this->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'profile',

                ],
            ]);

        $user->refresh();
        expect($user->name)->toBe('更新されたユーザー名');
        expect($user->profile->bio)->toBe('新しい自己紹介文');
        expect($user->profile->language)->toBe('en');
        expect($user->profile->timezone)->toBe('America/New_York');
        expect($user->profile->gender)->toBe('male');
        expect($user->profile->country)->toBe('US');
    });

    test('部分的なプロフィール更新ができる', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $originalName = $user->name;
        $originalBio = $user->profile->bio;

        $updateData = [
            'language' => 'en',
        ];

        $response = $this->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'user',
            ]);

        $user->refresh();
        expect($user->name)->toBe($originalName); // 変更されない
        expect($user->profile->bio)->toBe($originalBio); // 変更されない
        expect($user->profile->language)->toBe('en'); // 変更される
    });

    test('未認証のユーザーはプロフィールを取得できない', function () {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    });

    test('未認証のユーザーはプロフィールを更新できない', function () {
        $response = $this->putJson('/api/auth/profile', [
            'name' => '新しい名前',
        ]);

        $response->assertStatus(401);
    });

    test('無効なデータではプロフィール更新ができない', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $updateData = [
            'language' => 'invalid_lang', // 無効な言語コード
            'name' => str_repeat('a', 256), // 長すぎる名前
            'bio' => str_repeat('a', 501), // 長すぎる自己紹介
        ];

        $response = $this->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language', 'name', 'bio']);
    });
});
