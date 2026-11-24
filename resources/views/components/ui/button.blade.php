@props([
    'variant' => 'secondary', // primary, accent, danger, secondary
    'size' => 'md',           // xs, sm, md, lg
    'icon' => null,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-1.5 font-medium rounded-lg transition-all duration-150 cursor-pointer select-none active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none disabled:cursor-not-allowed';

    $sizeClasses = match($size) {
        'xs' => 'px-2 py-1 text-xs leading-none',
        'sm' => 'px-2.5 py-1.5 text-xs',
        'lg' => 'px-5 py-2.5 text-base',
        default => 'px-3.5 py-2 text-sm',
    };

    $variantClasses = match($variant) {
        'primary' => 'bg-[#238636] hover:bg-[#2ea043] border border-[#2ea043] text-white shadow-sm shadow-[#238636]/20',
        'accent' => 'bg-[#1f6feb] hover:bg-[#388bfd] border border-[#388bfd] text-white shadow-sm shadow-[#1f6feb]/20',
        'danger' => 'bg-[#da3633] hover:bg-[#f85149] border border-[#f85149] text-white shadow-sm shadow-[#da3633]/20',
        default => 'bg-[#21262d] hover:bg-[#30363d] border border-[#30363d] text-[#e6edf3] hover:text-white',
    };

    $classes = "{$baseClasses} {$sizeClasses} {$variantClasses}";
@endphp

<button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>
    @if ($icon)
        @if (is_string($icon) && !str_contains($icon, '<svg') && !str_contains($icon, '<span'))
            <x-icon :name="$icon" class="w-4 h-4 shrink-0" />
        @else
            <span class="shrink-0">{{ $icon }}</span>
        @endif
    @endif
    {{ $slot }}
</button>
