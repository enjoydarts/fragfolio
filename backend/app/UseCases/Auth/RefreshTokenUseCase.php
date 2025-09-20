<?php

namespace App\UseCases\Auth;

use App\Models\User;

class RefreshTokenUseCase
{
    public function execute(User $user): array
    {
        // 古いトークンを削除（存在する場合のみ）
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        // 新しいトークンを発行
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->load('profile', 'roles'),
            'token' => $token,
        ];
    }
}
