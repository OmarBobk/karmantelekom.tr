<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\SectionPosition;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        // Randomly choose either wholesale or retail
        $isWholesale = $this->faker->boolean();

        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'order' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
            'scrollable' => false,
            'position' => $this->faker->randomElement(SectionPosition::cases())->value,
            'is_wholesale_active' => $isWholesale,
            'is_retail_active' => !$isWholesale,
        ];
    }

    /**
     * Indicate that the section is wholesale active.
     */
    public function wholesaleActive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_wholesale_active' => true,
            'is_retail_active' => false,
        ]);
    }

    /**
     * Indicate that the section is retail active.
     */
    public function retailActive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_wholesale_active' => false,
            'is_retail_active' => true,
        ]);
    }
}
