@props([
    'label' => null,
    'error' => null,
    'icon' => null,
])

<div class="w-full">
    @if ($label)
        <label class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-1.5">
            {{ $label }}
        </label>
    @endif
    <div class="relative">
        @if ($icon)
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#8b949e]">
                @if (is_string($icon) && !str_contains($icon, '<svg') && !str_contains($icon, '<span'))
                    <x-icon :name="$icon" class="w-4 h-4 shrink-0" />
                @else
                    {{ $icon }}
                @endif
            </div>
        @endif
        <input {{ $attributes->merge([
            'class' => 'w-full bg-[#0d1117] border ' . ($error ? 'border-[#f85149]' : 'border-[#30363d]') . ' rounded-lg py-2 ' . ($icon ? 'pl-9 pr-3' : 'px-3') . ' text-sm text-[#e6edf3] placeholder-[#6e7681] transition-all focus:outline-none focus:border-[#58a6ff] focus:ring-2 focus:ring-[#58a6ff]/15 disabled:opacity-50 disabled:bg-[#161b22]'
        ]) }}>
    </div>
    @if ($error)
        <p class="mt-1 text-xs text-[#f85149]">{{ $error }}</p>
    @endif
</div>
