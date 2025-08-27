<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case DELIVERING = 'delivering';
    case DELIVERED = 'delivered';
    case CANCELED = 'canceled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getBackgroundColor(): string
    {
        return match($this) {
            self::PENDING => 'bg-yellow-100',
            self::CONFIRMED => 'bg-blue-100',
            self::PROCESSING => 'bg-purple-100',
            self::READY => 'bg-green-100',
            self::DELIVERING => 'bg-indigo-100',
            self::DELIVERED => 'bg-emerald-100',
            self::CANCELED => 'bg-red-100',
            default => 'bg-gray-100'
        };
    }

    public function getTextColor(): string
    {
        return match($this) {
            self::PENDING => 'text-yellow-800',
            self::CONFIRMED => 'text-blue-800',
            self::PROCESSING => 'text-purple-800',
            self::READY => 'text-green-800',
            self::DELIVERING => 'text-indigo-800',
            self::DELIVERED => 'text-emerald-800',
            self::CANCELED => 'text-red-800',
            default => 'text-gray-800'
        };
    }

    public function getProgressColor(): string
    {
        return match($this) {
            self::PENDING => 'bg-yellow-500',
            self::CONFIRMED => 'bg-blue-500',
            self::PROCESSING => 'bg-orange-500',
            self::READY => 'bg-purple-500',
            self::DELIVERING => 'bg-teal-500',
            self::DELIVERED => 'bg-green-500',
            self::CANCELED => 'bg-red-500',
            default => 'bg-gray-500'
        };
    }

    public function getProgressIconColor(): string
    {
        return match($this) {
            self::PENDING => 'text-white',
            self::CONFIRMED => 'text-white',
            self::PROCESSING => 'text-white',
            self::READY => 'text-white',
            self::DELIVERING => 'text-white',
            self::DELIVERED => 'text-white',
            self::CANCELED => 'text-white',
            default => 'text-white'
        };
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::PROCESSING => 'Processing',
            self::READY => 'Ready',
            self::DELIVERING => 'Delivering',
            self::DELIVERED => 'Delivered',
            self::CANCELED => 'Canceled',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::PENDING => 'Awaiting confirmation',
            self::CONFIRMED => 'Order has been confirmed',
            self::PROCESSING => 'Being prepared for shipment',
            self::READY => 'Ready for pickup or delivery',
            self::DELIVERING => 'Out for delivery',
            self::DELIVERED => 'Order delivered',
            self::CANCELED => 'Order canceled',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::PENDING => '<svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>',
            self::CONFIRMED => '<svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            self::PROCESSING => '<svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>',
            self::READY => '<svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            self::DELIVERING => '<svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h1l2 7h13l2-7h1"/></svg>',
            self::DELIVERED => '<svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            self::CANCELED => '<svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
        };
    }

    public function colorClasses(): string
    {
        return match($this) {
            self::PENDING => 'bg-yellow-50 text-yellow-700 ring-yellow-200',
            self::CONFIRMED => 'bg-blue-50 text-blue-700 ring-blue-200',
            self::PROCESSING => 'bg-purple-50 text-purple-700 ring-purple-200',
            self::READY => 'bg-green-50 text-green-700 ring-green-200',
            self::DELIVERING => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            self::DELIVERED => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::CANCELED => 'bg-red-50 text-red-700 ring-red-200',
        };
    }

    public function getProgressStep(): int
    {
        return match($this) {
            self::PENDING => 1,
            self::CONFIRMED => 2,
            self::PROCESSING => 3,
            self::READY => 4,
            self::DELIVERING => 5,
            self::DELIVERED => 6,
            self::CANCELED => 0, // Canceled orders don't follow the normal flow
        };
    }

    public function getProgressPercentage(): int
    {
        return match($this) {
            self::PENDING => 16,
            self::CONFIRMED => 33,
            self::PROCESSING => 50,
            self::READY => 66,
            self::DELIVERING => 83,
            self::DELIVERED => 100,
            self::CANCELED => 0,
        };
    }
}
