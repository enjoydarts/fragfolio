<?php

namespace App\UseCases\Fragrance;

use App\Models\UserFragrance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateFragranceUseCase
{
    /**
     * ����n�4�1���
     *
     * @param  UserFragrance  $userFragrance  ���an�4
     * @param  array  $data  �����
     * @return UserFragrance ��U�_�4
     *
     * @throws \Exception
     */
    public function execute(UserFragrance $userFragrance, array $data): UserFragrance
    {
        try {
            return DB::transaction(function () use ($userFragrance, $data) {
                // �,�1n��
                $userFragrance->update([
                    'purchase_date' => $data['purchase_date'] ?? $userFragrance->purchase_date,
                    'volume_ml' => $data['volume_ml'] ?? $userFragrance->volume_ml,
                    'purchase_price' => $data['purchase_price'] ?? $userFragrance->purchase_price,
                    'purchase_place' => $data['purchase_place'] ?? $userFragrance->purchase_place,
                    'possession_type' => $data['possession_type'] ?? $userFragrance->possession_type,
                    'duration_hours' => $data['duration_hours'] ?? $userFragrance->duration_hours,
                    'projection' => $data['projection'] ?? $userFragrance->projection,
                    'user_rating' => $data['user_rating'] ?? $userFragrance->user_rating,
                    'comments' => $data['comments'] ?? $userFragrance->comments,
                ]);

                // ��n��
                if (isset($data['tags'])) {
                    $userFragrance->tags()->delete();
                    foreach ($data['tags'] as $tagName) {
                        $userFragrance->tags()->create(['tag_name' => trim($tagName)]);
                    }
                }

                Log::info('Fragrance updated successfully', [
                    'user_fragrance_id' => $userFragrance->id,
                    'user_id' => $userFragrance->user_id,
                ]);

                return $userFragrance->load(['fragrance.brand', 'tags']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update fragrance', [
                'user_fragrance_id' => $userFragrance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
