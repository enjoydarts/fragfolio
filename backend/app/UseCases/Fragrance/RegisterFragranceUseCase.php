<?php

namespace App\UseCases\Fragrance;

use App\Models\Brand;
use App\Models\Fragrance;
use App\Models\User;
use App\Models\UserFragrance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegisterFragranceUseCase
{
    /**
     * ユーザーの香水コレクションに香水を登録
     *
     * @param  User  $user  ユーザー
     * @param  array  $data  香水データ
     * @return UserFragrance 登録された香水
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function execute(User $user, array $data): UserFragrance
    {
        // 入力データのバリデーション
        $this->validateInput($data);

        try {
            return DB::transaction(function () use ($user, $data) {
                // 1. ブランドを検索または作成
                $brand = $this->findOrCreateBrand(
                    $data['brand_name'],
                    $data['brand_name_en'] ?? null
                );

                // 2. 香水を検索または作成
                $fragrance = $this->findOrCreateFragrance(
                    $brand,
                    $data['fragrance_name'],
                    $data['fragrance_name_en'] ?? null
                );

                // 3. ユーザーの香水コレクションに追加
                $userFragrance = $this->createUserFragrance($user, $fragrance, $data);

                // 4. タグの追加
                if (! empty($data['tags'])) {
                    $this->attachTags($userFragrance, $data['tags']);
                }

                Log::info('Fragrance registered successfully', [
                    'user_id' => $user->id,
                    'user_fragrance_id' => $userFragrance->id,
                    'brand' => $brand->name_ja,
                    'fragrance' => $fragrance->name_ja,
                ]);

                return $userFragrance->load(['fragrance.brand', 'tags']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to register fragrance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 入力データのバリデーション
     *
     * @throws \InvalidArgumentException
     */
    private function validateInput(array $data): void
    {
        if (empty($data['brand_name'])) {
            throw new \InvalidArgumentException('Brand name is required');
        }

        if (empty($data['fragrance_name'])) {
            throw new \InvalidArgumentException('Fragrance name is required');
        }

        if (empty($data['possession_type'])) {
            throw new \InvalidArgumentException('Possession type is required');
        }
    }

    /**
     * ブランドを検索または作成
     */
    private function findOrCreateBrand(string $nameJa, ?string $nameEn): Brand
    {
        // まず完全一致で検索
        $brand = Brand::where('name_ja', $nameJa)->first();

        if ($brand) {
            // 英語名が提供されていて、既存のブランドに英語名がない場合は更新
            if ($nameEn && (! $brand->name_en || $brand->name_en === '')) {
                $brand->update(['name_en' => $nameEn]);
            }

            return $brand;
        }

        // 見つからなければ新規作成
        return Brand::create([
            'name_ja' => $nameJa,
            'name_en' => $nameEn ?? $nameJa,
        ]);
    }

    /**
     * 香水を検索または作成
     */
    private function findOrCreateFragrance(Brand $brand, string $nameJa, ?string $nameEn): Fragrance
    {
        // 同じブランドの同じ名前の香水を検索
        $fragrance = Fragrance::where('brand_id', $brand->id)
            ->where('name_ja', $nameJa)
            ->first();

        if ($fragrance) {
            // 英語名が提供されていて、既存の香水に英語名がない場合は更新
            if ($nameEn && (! $fragrance->name_en || $fragrance->name_en === '')) {
                $fragrance->update(['name_en' => $nameEn]);
            }

            return $fragrance;
        }

        // 見つからなければ新規作成
        return Fragrance::create([
            'brand_id' => $brand->id,
            'name_ja' => $nameJa,
            'name_en' => $nameEn ?? $nameJa,
            // concentration_type_idはオプショナルなのでnullのまま
        ]);
    }

    /**
     * ユーザーの香水コレクションに追加
     */
    private function createUserFragrance(User $user, Fragrance $fragrance, array $data): UserFragrance
    {
        return UserFragrance::create([
            'user_id' => $user->id,
            'fragrance_id' => $fragrance->id,
            'purchase_date' => $data['purchase_date'] ?? null,
            'volume_ml' => $data['volume_ml'] ?? null,
            'purchase_price' => $data['purchase_price'] ?? null,
            'purchase_place' => $data['purchase_place'] ?? null,
            'current_volume_ml' => $data['volume_ml'] ?? null, // 初期値は購入時の容量
            'possession_type' => $data['possession_type'],
            'duration_hours' => $data['duration_hours'] ?? null,
            'projection' => $data['projection'] ?? null,
            'user_rating' => $data['user_rating'] ?? null,
            'comments' => $data['comments'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * タグを追加
     */
    private function attachTags(UserFragrance $userFragrance, array $tags): void
    {
        foreach ($tags as $tagName) {
            $userFragrance->tags()->create([
                'tag_name' => trim($tagName),
            ]);
        }
    }
}
