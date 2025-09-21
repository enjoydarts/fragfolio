<?php

namespace App\UseCases\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserUseCase
{
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // ユーザー作成
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => null, // メール認証が必要
            ]);

            // ユーザープロフィールも作成
            $user->profile()->create([
                'language' => $data['language'] ?? 'ja',
                'timezone' => $data['timezone'] ?? 'Asia/Tokyo',
            ]);

            // デフォルトで一般ユーザー権限を付与
            $user->assignRole('user');

            // メール認証通知を送信
            $user->sendEmailVerificationNotification();

            return $user->load('profile', 'roles');
        });
    }
}
