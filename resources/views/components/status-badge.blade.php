<div>
    <!-- Breathing in, I calm body and mind. Breathing out, I smile. - Thich Nhat Hanh -->
</div>

@php
    $map = [
        'pending' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'label' => 'Pending'],
        'confirmed' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Confirmed'],
        'processing' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'label' => 'Processing'],
        'ready' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'Ready'],
        'delivering' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'label' => 'Delivering'],
        'delivered' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Delivered'],
        'canceled' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'Canceled'],
    ];
    $s = $map[$status] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => ucfirst($status)];
@endphp
<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $s['bg'] }} {{ $s['text'] }}">
    {{ $s['label'] }}
</span>