<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            [
                'name' => 'New Arrival',
                'text_color' => '#FFFFFF',
                'background_color' => '#3B82F6',
                'border_color' => '#2563EB',
                'icon' => '⭐',
                'is_active' => true,
                'display_order' => 1
            ],
            [
                'name' => 'Best Seller',
                'text_color' => '#FFFFFF',
                'background_color' => '#10B981',
                'border_color' => '#059669',
                'icon' => '🔥',
                'is_active' => true,
                'display_order' => 2
            ],
            [
                'name' => 'Hot Deal',
                'text_color' => '#FFFFFF',
                'background_color' => '#EF4444',
                'border_color' => '#DC2626',
                'icon' => '💥',
                'is_active' => true,
                'display_order' => 3
            ],
            [
                'name' => 'Featured',
                'text_color' => '#FFFFFF',
                'background_color' => '#8B5CF6',
                'border_color' => '#7C3AED',
                'icon' => '✨',
                'is_active' => true,
                'display_order' => 4
            ],
            [
                'name' => 'Sale',
                'text_color' => '#FFFFFF',
                'background_color' => '#F59E0B',
                'border_color' => '#D97706',
                'icon' => '🏷️',
                'is_active' => true,
                'display_order' => 5
            ]
        ];

        foreach ($tags as $tag) {
            Tag::create($tag);
        }
    }
}
