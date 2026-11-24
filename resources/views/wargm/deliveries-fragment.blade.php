<div class="panel p-5 sm:p-6 rounded-2xl border border-[#30363d] bg-[#161b22] shadow-xl">
    <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-[#21262d] flex-wrap gap-2">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center text-[#58a6ff]">
                <x-icon name="shopping-cart" class="w-4 h-4" />
            </span>
            <h2 class="font-bold text-base text-[#e6edf3]">История выдач WARGM</h2>
        </div>
        <span class="badge badge-blue">{{ count($deliveries) }} выдач</span>
    </div>

    @if (count($deliveries) === 0)
        <div class="text-sm text-[#8b949e] text-center py-12 px-4">
            <div class="w-12 h-12 rounded-2xl bg-[#21262d]/50 border border-[#30363d] flex items-center justify-center mx-auto mb-2 text-[#8b949e] opacity-60">
                <x-icon name="package" class="w-6 h-6" />
            </div>
            <div class="font-semibold text-[#e6edf3]">Выдач для этого игрока пока нет</div>
            <div class="text-xs text-[#8b949e] mt-1">Здесь фиксируются все покупки и доставки через шлюз WARGM.</div>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-[#21262d] bg-[#0d1117]">
            <table class="table min-w-full">
                <thead>
                    <tr class="bg-[#101623] text-[11px] font-semibold text-[#8b949e] uppercase tracking-wider border-b border-[#21262d]">
                        <th class="py-2.5 px-3.5">#</th>
                        <th class="py-2.5 px-3.5">Purchase ID</th>
                        <th class="py-2.5 px-3.5">Товар / Карточка</th>
                        <th class="py-2.5 px-3.5 text-right">Дата и время</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#21262d]">
                    @foreach ($deliveries as $d)
                        <tr class="hover:bg-[#161b22]/70 transition-colors">
                            <td class="py-2.5 px-3.5 text-xs text-[#8b949e] font-mono">{{ $d['id'] }}</td>
                            <td class="py-2.5 px-3.5 mono text-xs text-[#58a6ff]">{{ $d['purchaseId'] }}</td>
                            <td class="py-2.5 px-3.5 text-sm font-medium text-[#e6edf3]">
                                <span class="flex items-center gap-1.5">
                                    <x-icon name="package" class="w-3.5 h-3.5 text-[#e3b341]" />
                                    <span>{{ $d['cardName'] ?: 'Товар удален' }}</span>
                                </span>
                            </td>
                            <td class="py-2.5 px-3.5 text-xs text-[#8b949e] text-right font-mono">{{ $d['deliveredAt'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
