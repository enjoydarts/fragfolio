<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function __construct(
        private TurnstileService $turnstileService
    ) {}

    /**
     * 新規ユーザーのバリデーションと作成
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'cf-turnstile-response' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Turnstile検証
        if (! $this->turnstileService->verify($input['cf-turnstile-response'], request()->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => ['認証に失敗しました。'],
            ]);
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'role' => 'user',
        ]);

        // プロフィール作成
        $user->profile()->create([
            'language' => $input['language'] ?? 'ja',
            'timezone' => $input['timezone'] ?? 'Asia/Tokyo',
        ]);

        return $user;
    }
}
