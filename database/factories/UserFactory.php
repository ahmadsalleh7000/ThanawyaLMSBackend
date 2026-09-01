<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone_e164' => null,
            'phone_verified_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'registration_status' => 'approved',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withEgyptianPhone(?string $phone = null): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_e164' => $phone ?? '+201'.fake()->randomElement(['0', '1', '2', '5']).fake()->unique()->numerify('########'),
        ]);
    }

    public function phoneVerified(): static
    {
        return $this->withEgyptianPhone()->state(fn (array $attributes) => [
            'phone_verified_at' => now(),
        ]);
    }
}
