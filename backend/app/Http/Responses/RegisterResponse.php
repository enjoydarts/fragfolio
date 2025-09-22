<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract, Responsable
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        $user = $request->user()->load('profile');

        // Sanctumトークンを生成
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('auth.registration_success'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                'profile' => [
                    'language' => $user->profile?->language ?? 'ja',
                    'timezone' => $user->profile?->timezone ?? 'Asia/Tokyo',
                    'bio' => $user->profile?->bio ?? null,
                    'date_of_birth' => $user->profile?->date_of_birth ?? null,
                    'gender' => $user->profile?->gender ?? null,
                    'country' => $user->profile?->country ?? null,
                ],
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'token' => $token,
        ], 201);
    }
}
