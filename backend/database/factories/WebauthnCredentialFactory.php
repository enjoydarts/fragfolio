<?php

namespace Database\Factories;

use App\Models\WebauthnCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebauthnCredentialFactory extends Factory
{
    protected $model = WebauthnCredential::class;

    public function definition()
    {
        return [
            'authenticatable_id' => User::factory(),
            'authenticatable_type' => User::class,
            'id' => $this->faker->uuid(),
            'user_id' => function (array $attributes) {
                $user = \App\Models\User::find($attributes['authenticatable_id']);
                return $user ? $user->webAuthnId()->toString() : $this->faker->uuid();
            },
            'counter' => 0,
            'rp_id' => 'localhost',
            'origin' => 'http://localhost',
            'aaguid' => null,
            'attestation_format' => 'none',
            'public_key' => $this->faker->text(100),
            'alias' => $this->faker->words(2, true),
            'disabled_at' => null,
        ];
    }

    public function forUser(User $user)
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'authenticatable_id' => $user->id,
                'user_id' => $user->webAuthnId()->toString(),
            ];
        });
    }

    public function disabled()
    {
        return $this->state(function (array $attributes) {
            return [
                'disabled_at' => now(),
            ];
        });
    }
}