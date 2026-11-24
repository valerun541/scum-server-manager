@props([
    'variant' => 'gray', // green, red, yellow, blue, purple, gray
    'size' => 'sm',
    'dot' => false,
])

@php
    $sizeClass = $size === 'xs' ? 'text-[10px] px-1.5 py-0.5' : 'text-xs px-2.5 py-0.5';

    $variantClasses = match($variant) {
        'green' => 'bg-[#238636]/15 text-[#3fb950] border-[#2ea043]/30',
        'red' => 'bg-[#da3633]/15 text-[#f85149] border-[#f85149]/30',
        'yellow' => 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30',
        'blue' => 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#58a6ff]/30',
        'purple' => 'bg-[#a371f7]/15 text-[#d2a8ff] border-[#a371f7]/30',
        default => 'bg-[#21262d] text-[#8b949e] border-[#30363d]',
    };

    $dotColor = match($variant) {
        'green' => 'bg-[#3fb950]',
        'red' => 'bg-[#f85149]',
        'yellow' => 'bg-[#d29922]',
        'blue' => 'bg-[#58a6ff]',
        'purple' => 'bg-[#d2a8ff]',
        default => 'bg-[#8b949e]',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 font-semibold rounded-full border {$sizeClass} {$variantClasses}"]) }}>
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }} shrink-0"></span>
    @endif
    {{ $slot }}
</span>
