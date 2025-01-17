<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $colors = [
            ['text' => '#FFFFFF', 'bg' => '#3B82F6', 'border' => '#2563EB'], // Blue
            ['text' => '#FFFFFF', 'bg' => '#10B981', 'border' => '#059669'], // Green
            ['text' => '#FFFFFF', 'bg' => '#EF4444', 'border' => '#DC2626'], // Red
            ['text' => '#FFFFFF', 'bg' => '#F59E0B', 'border' => '#D97706'], // Yellow
            ['text' => '#FFFFFF', 'bg' => '#8B5CF6', 'border' => '#7C3AED'], // Purple
        ];

        $randomColor = $this->faker->randomElement($colors);

        return [
            'name' => $this->faker->word,
            'text_color' => $randomColor['text'],
            'background_color' => $randomColor['bg'],
            'border_color' => $randomColor['border'],
            'icon' => $this->faker->randomElement(['🏷️', '⭐', '🔥', '✨', '💎']),
            'is_featured' => $this->faker->boolean(20),
            'display_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
