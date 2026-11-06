<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TrackingMode;
use App\Models\ProductDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDefinition>
 */
class ProductDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'model' => $this->faker->optional()->bothify('??-###'),
            'tracking_mode' => TrackingMode::QUANTITY,
            'is_active' => true,
            'sku' => null,
            'barcode' => null,
        ];
    }

    public function serialized(): static
    {
        return $this->state(['tracking_mode' => TrackingMode::SERIALIZED]);
    }

    public function usedDevice(): static
    {
        return $this->state(['tracking_mode' => TrackingMode::USED_DEVICE]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function priced(string $sellingPrice): static
    {
        return $this->state(['selling_price' => $sellingPrice]);
    }
}
