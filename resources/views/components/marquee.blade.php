@props([
    'testimonials' => [
        [
            'name' => 'Jack',
            'username' => '@jack',
            'avatar' => 'https://avatar.vercel.sh/jack',
            'quote' => 'I\'ve never seen anything like this before. It\'s amazing. I love it.'
        ],
        [
            'name' => 'Jill',
            'username' => '@jill',
            'avatar' => 'https://avatar.vercel.sh/jill',
            'quote' => 'I don\'t know what to say. I\'m speechless. This is amazing.'
        ],
        [
            'name' => 'John',
            'username' => '@john',
            'avatar' => 'https://avatar.vercel.sh/john',
            'quote' => 'I\'m at a loss for words. This is amazing. I love it.'
        ]
    ]
])

<div class="w-full bg-white py-4">
    <div class="group flex overflow-hidden p-2 [--gap:1rem] [gap:var(--gap)] flex-row [--duration:20s]">
        @foreach(range(1, 2) as $iteration)
            <div class="flex shrink-0 justify-around [gap:var(--gap)] animate-marquee flex-row group-hover:[animation-play-state:paused]">
                @foreach($testimonials as $testimonial)
                    <figure class="relative h-full w-64 cursor-pointer overflow-hidden rounded-xl border p-4 border-gray-950/[.1] bg-gray-950/[.01] hover:bg-gray-950/[.05] dark:border-gray-50/[.1] dark:bg-gray-50/[.10] dark:hover:bg-gray-50/[.15]">
                        <div class="flex flex-row items-center gap-2">
                            <img class="rounded-full" width="32" height="32" alt="" src="{{ $testimonial['avatar'] }}">
                            <div class="flex flex-col">
                                <figcaption class="text-sm font-medium dark:text-white">{{ $testimonial['name'] }}</figcaption>
                                <p class="text-xs font-medium dark:text-white/40">{{ $testimonial['username'] }}</p>
                            </div>
                        </div>
                        <blockquote class="mt-2 text-sm">{{ $testimonial['quote'] }}</blockquote>
                    </figure>
                @endforeach
            </div>
        @endforeach
        @foreach(range(1, 2) as $iteration)
            <div class="flex shrink-0 justify-around [gap:var(--gap)] animate-marquee flex-row group-hover:[animation-play-state:paused]">
                @foreach($testimonials as $testimonial)
                    <figure class="relative h-full w-64 cursor-pointer overflow-hidden rounded-xl border p-4 border-gray-950/[.1] bg-gray-950/[.01] hover:bg-gray-950/[.05] dark:border-gray-50/[.1] dark:bg-gray-50/[.10] dark:hover:bg-gray-50/[.15]">
                        <div class="flex flex-row items-center gap-2">
                            <img class="rounded-full" width="32" height="32" alt="" src="{{ $testimonial['avatar'] }}">
                            <div class="flex flex-col">
                                <figcaption class="text-sm font-medium dark:text-white">{{ $testimonial['name'] }}</figcaption>
                                <p class="text-xs font-medium dark:text-white/40">{{ $testimonial['username'] }}</p>
                            </div>
                        </div>
                        <blockquote class="mt-2 text-sm">{{ $testimonial['quote'] }}</blockquote>
                    </figure>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

@push('styles')
<style>
    @keyframes marquee {
        from {
            transform: translateX(0);
        }
        to {
            transform: translateX(calc(-100% - var(--gap)));
        }
    }

    .animate-marquee {
        animation: marquee var(--duration) linear infinite;
    }
</style>
@endpush
