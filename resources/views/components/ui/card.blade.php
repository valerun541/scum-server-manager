@props([
    'title' => null,
    'subtitle' => null,
    'headerClass' => '',
])

<div {{ $attributes->merge(['class' => 'bg-[#161b22] border border-[#30363d] rounded-xl shadow-sm transition-all']) }}>
    @if ($title || isset($header))
        <div class="px-5 py-3.5 border-b border-[#30363d] flex items-center justify-between gap-3 {{ $headerClass }}">
            @if (isset($header))
                {{ $header }}
            @else
                <div>
                    <h3 class="text-sm font-semibold text-[#e6edf3] tracking-wide">{{ $title }}</h3>
                    @if ($subtitle)
                        <p class="text-xs text-[#8b949e] mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif
            @if (isset($actions))
                <div class="flex items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
    @if (isset($footer))
        <div class="px-5 py-3 bg-[#0d1117]/50 border-t border-[#30363d] rounded-b-xl flex items-center justify-between">
            {{ $footer }}
        </div>
    @endif
</div>
