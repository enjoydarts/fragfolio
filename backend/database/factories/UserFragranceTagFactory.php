<?php

namespace Database\Factories;

use App\Models\UserFragrance;
use App\Models\UserFragranceTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserFragranceTag>
 */
class UserFragranceTagFactory extends Factory
{
    protected $model = UserFragranceTag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tags = [
            'フローラル',
            'シトラス',
            'ウッディ',
            'スパイシー',
            'オリエンタル',
            'フレッシュ',
            'スイート',
            'エレガント',
            'セクシー',
            'カジュアル',
            '夏向け',
            '冬向け',
            'デイリー',
            'フォーマル',
            'お気に入り',
        ];

        return [
            'user_fragrance_id' => UserFragrance::factory(),
            'tag_name' => fake()->randomElement($tags),
        ];
    }
}
