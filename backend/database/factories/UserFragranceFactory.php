<?php

namespace Database\Factories;

use App\Models\Fragrance;
use App\Models\User;
use App\Models\UserFragrance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserFragrance>
 */
class UserFragranceFactory extends Factory
{
    protected $model = UserFragrance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fragrance_id' => Fragrance::factory(),
            'purchase_date' => fake()->optional()->date(),
            'volume_ml' => fake()->optional()->randomFloat(2, 5, 200),
            'purchase_price' => fake()->optional()->numberBetween(1000, 50000),
            'purchase_place' => fake()->optional()->randomElement(['銀座', '新宿', '渋谷', '表参道', 'オンライン', '免税店']),
            'current_volume_ml' => null,
            'possession_type' => fake()->randomElement(['full_bottle', 'decant', 'sample']),
            'duration_hours' => fake()->optional()->numberBetween(2, 12),
            'projection' => fake()->optional()->randomElement(['weak', 'moderate', 'strong']),
            'user_rating' => fake()->optional()->numberBetween(1, 5),
            'comments' => fake()->optional()->sentence(),
            'bottle_image' => null,
            'box_image' => null,
            'is_active' => true,
        ];
    }
}
