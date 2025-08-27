@props(['currentStatus', 'orderStatus'])

@php
    $steps = [
        [
            'status' => \App\Enums\OrderStatus::PENDING,
            'label' => 'Pending',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'color' => 'bg-gray-300',
            'iconColor' => 'text-gray-600',
            'activeColor' => 'bg-yellow-500',
            'activeIconColor' => 'text-white'
        ],
        [
            'status' => \App\Enums\OrderStatus::CONFIRMED,
            'label' => 'Confirmed',
            'icon' => 'M5 13l4 4L19 7',
            'color' => 'bg-gray-300',
            'iconColor' => 'text-gray-600',
            'activeColor' => 'bg-blue-500',
            'activeIconColor' => 'text-white'
        ],
        [
            'status' => \App\Enums\OrderStatus::PROCESSING,
            'label' => 'Processing',
            'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            'color' => 'bg-gray-300',
            'iconColor' => 'text-gray-600',
            'activeColor' => 'bg-orange-500',
            'activeIconColor' => 'text-white'
        ],
        [
            'status' => \App\Enums\OrderStatus::READY,
            'label' => 'Shipped',
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'color' => 'bg-gray-300',
            'iconColor' => 'text-gray-600',
            'activeColor' => 'bg-purple-500',
            'activeIconColor' => 'text-white'
        ],
        [
            'status' => \App\Enums\OrderStatus::DELIVERING,
            'label' => 'Out for Delivery',
            'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
            'color' => 'bg-gray-300',
            'iconColor' => 'text-gray-600',
            'activeColor' => 'bg-teal-500',
            'activeIconColor' => 'text-white'
        ],
        [
            'status' => \App\Enums\OrderStatus::DELIVERED,
            'label' => 'Delivered',
            'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            'color' => 'bg-gray-300',
            'iconColor' => 'text-gray-600',
            'activeColor' => 'bg-green-500',
            'activeIconColor' => 'text-white'
        ]
    ];
@endphp

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <!-- Title -->
    <h5 class="text-sm font-semibold text-gray-700 mb-6 text-center">Classic Design</h5>
    
    <!-- Progress Tracker -->
    <div class="relative px-4">
        <!-- Progress Line -->
        <div class="absolute top-6 left-4 right-4 h-0.5 bg-gray-300 rounded-full"></div>
        
        <!-- Progress Steps -->
        <div class="relative flex justify-between items-start">
            @foreach($steps as $index => $step)
                @php
                    $isCompleted = $currentStatus->getProgressStep() >= $step['status']->getProgressStep();
                    $isCurrent = $currentStatus === $step['status'];
                    $isActive = $isCompleted || $isCurrent;
                    $isHighlighted = $isCurrent; // Current stage gets the blue ring highlight
                @endphp
                
                <div class="flex flex-col items-center min-w-0 flex-1 relative px-2">
                    <!-- Step Circle -->
                    <div class="relative z-10 flex items-center justify-center w-12 h-12 rounded-full border-2 transition-all duration-300 
                        {{ $isActive ? $step['activeColor'] . ' border-transparent shadow-lg' : 'bg-gray-100 border-gray-300' }} 
                        {{ $isHighlighted ? 'ring-4 ring-blue-200' : '' }}"
                         style="margin-top: 0px;">
                        
                        <svg class="w-6 h-6 {{ $isActive ? $step['activeIconColor'] : $step['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"></path>
                        </svg>
                    </div>
                    
                    <!-- Step Label -->
                    <div class="mt-3 text-center px-1">
                        <span class="text-[10px] sm:text-xs md:text-sm font-medium 
                            {{ $isActive ? 'text-gray-900 font-semibold' : 'text-gray-500' }} 
                            break-words leading-tight">
                            {{ $step['label'] }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
