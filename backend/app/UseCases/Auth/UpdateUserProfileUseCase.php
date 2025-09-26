<?php

namespace App\UseCases\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserProfileUseCase
{
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // ユーザー基本情報更新
            if (isset($data['name'])) {
                $user->update(['name' => $data['name']]);
            }

            // プロフィール情報更新
            $profileData = array_filter($data, fn ($key) => in_array($key, [
                'bio', 'language', 'timezone', 'date_of_birth', 'gender', 'country',
            ]), ARRAY_FILTER_USE_KEY);

            if (! empty($profileData)) {
                $user->profile->update($profileData);
            }

            return $user->fresh()->load('profile');
        });
    }
}
