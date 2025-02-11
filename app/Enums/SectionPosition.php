<?php

namespace App\Enums;

enum SectionPosition: string
{
    case MAIN_SLIDER = 'main.slider';
    case MAIN_CONTENT = 'main.content';
    case PRODUCTS_TOP = 'products.top';
    case PRODUCTS_FOOTER = 'products.footer';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getBackgroundColor(): string
    {
        return match($this) {
            self::MAIN_SLIDER => 'bg-blue-100 text-blue-800',
            self::MAIN_CONTENT => 'bg-green-100 text-green-800',
            self::PRODUCTS_TOP => 'bg-purple-100 text-purple-800',
            self::PRODUCTS_FOOTER => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
} 