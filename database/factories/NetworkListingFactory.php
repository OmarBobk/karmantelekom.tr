<?php

namespace Database\Factories;

use App\Models\NetworkListing;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Units are created through inventory actions, so callers pass `inventory_unit_id` and its store.
 *
 * @extends Factory<NetworkListing>
 */
class NetworkListingFactory extends Factory
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
            'published_by' => User::factory(),
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'unpublished_by' => $attributes['published_by'],
            'unpublished_at' => now(),
        ]);
    }
}
