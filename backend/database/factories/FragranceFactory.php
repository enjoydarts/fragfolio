<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Fragrance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fragrance>
 */
class FragranceFactory extends Factory
{
    protected $model = Fragrance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fragranceNames = [
            ['ja' => 'No.5', 'en' => 'No.5'],
            ['ja' => 'ソヴァージュ', 'en' => 'Sauvage'],
            ['ja' => 'ブラック オーキッド', 'en' => 'Black Orchid'],
            ['ja' => 'アヴァントゥス', 'en' => 'Aventus'],
            ['ja' => 'レプリカ レイジーサンデーモーニング', 'en' => 'Replica Lazy Sunday Morning'],
            ['ja' => 'ジプシーウォーター', 'en' => 'Gypsy Water'],
        ];

        $name = fake()->randomElement($fragranceNames);

        return [
            'brand_id' => Brand::factory(),
            'name_ja' => $name['ja'],
            'name_en' => $name['en'],
            'description_ja' => fake()->optional()->sentence(),
            'description_en' => fake()->optional()->sentence(),
            'concentration_type_id' => null,
            'release_year' => fake()->optional()->year(),
            'image' => null,
            'is_discontinued' => fake()->boolean(10),
            'is_active' => true,
        ];
    }
}
