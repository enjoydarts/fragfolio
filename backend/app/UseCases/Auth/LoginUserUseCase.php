<?php

namespace App\UseCases\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class LoginUserUseCase
{
    public function execute(array $credentials, bool $remember = false): array
    {
        if (! Auth::attempt($credentials)) {
            throw new AuthenticationException('認証情報が正しくありません');
        }

        $user = Auth::user();
        $tokenName = $remember ? 'remember_token' : 'auth_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user->load('profile', 'roles'),
            'token' => $token,
        ];
    }
}
