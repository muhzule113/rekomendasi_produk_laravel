<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp' => '08' . fake()->numerify('##########'),
            'alamat' => fake()->address(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'pelanggan',
            'status' => 'aktif',
            'created_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'admin',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'nonaktif',
        ]);
    }
}
