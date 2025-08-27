<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Progress Tracker Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Order Progress Tracker Demo</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Pending State -->
            <div>
                <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">Pending State</h2>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <!-- Title -->
                    <h5 class="text-sm font-semibold text-gray-700 mb-6 text-center">Classic Design</h5>
                    
                    <!-- Progress Tracker -->
                    <div class="relative px-4">
                        <!-- Progress Line -->
                        <div class="absolute top-6 left-4 right-4 h-0.5 bg-gray-300 rounded-full"></div>
                        
                        <!-- Progress Steps -->
                        <div class="relative flex justify-between items-start">
                            @php
                                $steps = [
                                    [
                                        'status' => 'pending',
                                        'label' => 'Pending',
                                        'icon' => 'M12 6v6l4 2',
                                        'isActive' => true,
                                        'isCompleted' => false,
                                        'color' => 'bg-yellow-500',
                                        'iconColor' => 'text-white'
                                    ],
                                    [
                                        'status' => 'confirmed',
                                        'label' => 'Confirmed',
                                        'icon' => 'M5 13l4 4L19 7',
                                        'isActive' => false,
                                        'isCompleted' => false,
                                        'color' => 'bg-gray-100',
                                        'iconColor' => 'text-gray-600'
                                    ],
                                    [
                                        'status' => 'processing',
                                        'label' => 'Processing',
                                        'icon' => 'M12 6v6l4 2',
                                        'isActive' => false,
                                        'isCompleted' => false,
                                        'color' => 'bg-gray-100',
                                        'iconColor' => 'text-gray-600'
                                    ],
                                    [
                                        'status' => 'shipped',
                                        'label' => 'Shipped',
                                        'icon' => 'M3 10h1l2 7h13l2-7h1',
                                        'isActive' => false,
                                        'isCompleted' => false,
                                        'color' => 'bg-gray-100',
                                        'iconColor' => 'text-gray-600'
                                    ],
                                    [
                                        'status' => 'delivering',
                                        'label' => 'Out for Delivery',
                                        'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z',
                                        'isActive' => false,
                                        'isCompleted' => false,
                                        'color' => 'bg-gray-100',
                                        'iconColor' => 'text-gray-600'
                                    ],
                                    [
                                        'status' => 'delivered',
                                        'label' => 'Delivered',
                                        'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                                        'isActive' => false,
                                        'isCompleted' => false,
                                        'color' => 'bg-gray-100',
                                        'iconColor' => 'text-gray-600'
                                    ]
                                ];
                            @endphp

                            @foreach($steps as $step)
                                <div class="flex flex-col items-center min-w-0 flex-1 relative px-2">
                                    <!-- Step Circle -->
                                    <div class="relative z-10 flex items-center justify-center w-12 h-12 rounded-full border-2 transition-all duration-300 
                                        {{ $step['color'] }} {{ $step['isActive'] ? 'border-transparent shadow-lg ring-4 ring-blue-200' : 'border-gray-300' }}"
                                         style="margin-top: 0px;">
                                        
                                        <svg class="w-6 h-6 {{ $step['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"></path>
                                        </svg>
                                    </div>
                                    
                                    <!-- Step Label -->
                                    <div class="mt-3 text-center px-1">
                                        <span class="text-[10px] sm:text-xs md:text-sm font-medium 
                                            {{ $step['isActive'] ? 'text-gray-900 font-semibold' : 'text-gray-500' }} 
                                            break-words leading-tight">
                                            {{ $step['label'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivered State -->
            <div>
                <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">Delivered State</h2>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <!-- Title -->
                    <h5 class="text-sm font-semibold text-gray-700 mb-6 text-center">Classic Design</h5>
                    
                    <!-- Progress Tracker -->
                    <div class="relative px-4">
                        <!-- Progress Line -->
                        <div class="absolute top-6 left-4 right-4 h-0.5 bg-green-500 rounded-full"></div>
                        
                        <!-- Progress Steps -->
                        <div class="relative flex justify-between items-start">
                            @php
                                $steps = [
                                    [
                                        'status' => 'pending',
                                        'label' => 'Pending',
                                        'icon' => 'M12 6v6l4 2',
                                        'isActive' => false,
                                        'isCompleted' => true,
                                        'color' => 'bg-yellow-500',
                                        'iconColor' => 'text-white'
                                    ],
                                    [
                                        'status' => 'confirmed',
                                        'label' => 'Confirmed',
                                        'icon' => 'M5 13l4 4L19 7',
                                        'isActive' => false,
                                        'isCompleted' => true,
                                        'color' => 'bg-blue-500',
                                        'iconColor' => 'text-white'
                                    ],
                                    [
                                        'status' => 'processing',
                                        'label' => 'Processing',
                                        'icon' => 'M12 6v6l4 2',
                                        'isActive' => false,
                                        'isCompleted' => true,
                                        'color' => 'bg-orange-500',
                                        'iconColor' => 'text-white'
                                    ],
                                    [
                                        'status' => 'shipped',
                                        'label' => 'Shipped',
                                        'icon' => 'M3 10h1l2 7h13l2-7h1',
                                        'isActive' => false,
                                        'isCompleted' => true,
                                        'color' => 'bg-purple-500',
                                        'iconColor' => 'text-white'
                                    ],
                                    [
                                        'status' => 'delivering',
                                        'label' => 'Out for Delivery',
                                        'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z',
                                        'isActive' => false,
                                        'isCompleted' => true,
                                        'color' => 'bg-teal-500',
                                        'iconColor' => 'text-white'
                                    ],
                                    [
                                        'status' => 'delivered',
                                        'label' => 'Delivered',
                                        'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                                        'isActive' => true,
                                        'isCompleted' => true,
                                        'color' => 'bg-green-500',
                                        'iconColor' => 'text-white'
                                    ]
                                ];
                            @endphp

                            @foreach($steps as $step)
                                <div class="flex flex-col items-center min-w-0 flex-1 relative px-2">
                                    <!-- Step Circle -->
                                    <div class="relative z-10 flex items-center justify-center w-12 h-12 rounded-full border-2 transition-all duration-300 
                                        {{ $step['color'] }} {{ $step['isActive'] ? 'border-transparent shadow-lg ring-4 ring-blue-200' : 'border-transparent shadow-lg' }}"
                                         style="margin-top: 0px;">
                                        
                                        @if($step['isCompleted'])
                                            <svg class="w-6 h-6 {{ $step['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        @else
                                            <svg class="w-6 h-6 {{ $step['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $step['icon'] }}"></path>
                                            </svg>
                                        @endif
                                    </div>
                                    
                                    <!-- Step Label -->
                                    <div class="mt-3 text-center px-1">
                                        <span class="text-[10px] sm:text-xs md:text-sm font-medium 
                                            {{ $step['isActive'] ? 'text-gray-900 font-semibold' : 'text-gray-500' }} 
                                            break-words leading-tight">
                                            {{ $step['label'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Instructions -->
        <div class="mt-12 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Usage Instructions</h3>
            <div class="prose prose-sm max-w-none">
                <p class="text-gray-600 mb-4">
                    This progress tracker component can be used in your Laravel application to show order status progression.
                </p>
                
                <h4 class="text-md font-semibold text-gray-800 mb-2">Features:</h4>
                <ul class="list-disc list-inside text-gray-600 space-y-1 mb-4">
                    <li>Responsive design that works on all screen sizes</li>
                    <li>Color-coded stages with distinct icons</li>
                    <li>Blue ring highlight for the current active stage</li>
                    <li>Checkmark icons for completed stages</li>
                    <li>Gray line connecting all stages</li>
                    <li>Green line for completed progress</li>
                </ul>

                <h4 class="text-md font-semibold text-gray-800 mb-2">Tailwind CSS Classes Used:</h4>
                <div class="bg-gray-50 rounded-lg p-4 text-sm font-mono text-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="font-semibold mb-2">Layout:</p>
                            <ul class="space-y-1 text-xs">
                                <li>• flex justify-between items-start</li>
                                <li>• relative z-10</li>
                                <li>• min-w-0 flex-1</li>
                            </ul>
                        </div>
                        <div>
                            <p class="font-semibold mb-2">Styling:</p>
                            <ul class="space-y-1 text-xs">
                                <li>• w-12 h-12 rounded-full</li>
                                <li>• ring-4 ring-blue-200</li>
                                <li>• shadow-lg border-2</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
