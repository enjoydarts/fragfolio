<?php

namespace App\UseCases\Fragrance;

use App\Models\UserFragrance;
use Illuminate\Support\Facades\Log;

class DeleteFragranceUseCase
{
    /**
     * ����n�4��Jd
     *
     * @param  UserFragrance  $userFragrance  Jd�an�4
     * @return bool Jd�
     *
     * @throws \Exception
     */
    public function execute(UserFragrance $userFragrance): bool
    {
        try {
            $userFragrance->update(['is_active' => false]);

            Log::info('Fragrance deleted successfully', [
                'user_fragrance_id' => $userFragrance->id,
                'user_id' => $userFragrance->user_id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete fragrance', [
                'user_fragrance_id' => $userFragrance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
