<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'message' => fake()->optional()->paragraph(),
            'expires_at' => now()->addDays(7),
            'notify_on_download' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => TransferStatus::Ready,
            'published_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->published()->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function withPassword(string $password = 'geheim'): static
    {
        return $this->state(fn () => ['password' => $password]);
    }
}
