<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CardTerminal;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardTerminal>
 */
class CardTerminalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'label' => 'Terminal '.$this->faker->unique()->numberBetween(1, 999),
            'merchant_number' => $this->faker->numerify('########'),
            'terminal_number' => $this->faker->unique()->numerify('T#######'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
