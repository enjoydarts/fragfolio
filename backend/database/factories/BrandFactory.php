<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brandNames = [
            ['ja' => 'シャネル', 'en' => 'CHANEL'],
            ['ja' => 'ディオール', 'en' => 'Dior'],
            ['ja' => 'エルメス', 'en' => 'Hermès'],
            ['ja' => 'トム・フォード', 'en' => 'Tom Ford'],
            ['ja' => 'ジョー マローン', 'en' => 'Jo Malone'],
            ['ja' => 'クリード', 'en' => 'Creed'],
            ['ja' => 'メゾン マルジェラ', 'en' => 'Maison Margiela'],
            ['ja' => 'バイレード', 'en' => 'Byredo'],
        ];

        $brand = fake()->randomElement($brandNames);

        return [
            'name_ja' => $brand['ja'],
            'name_en' => $brand['en'],
            'description_ja' => fake()->optional()->sentence(),
            'description_en' => fake()->optional()->sentence(),
            'country' => fake()->optional()->countryCode(),
            'founded_year' => fake()->optional()->year(),
            'website' => fake()->optional()->url(),
            'logo' => null,
            'is_active' => true,
        ];
    }
}
