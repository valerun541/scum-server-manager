<div class="panel p-5 sm:p-6">
    <div class="flex items-center justify-between mb-5 pb-3 border-b border-[#21262d]">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-[#1f6feb]/15 border border-[#388bfd]/30 flex items-center justify-center"><x-icon name="user" class="w-4 h-4 text-[#58a6ff]" /></span>
            <h2 class="font-bold text-[#e6edf3] text-base">Информация об игроке (RCON)</h2>
        </div>
        <span class="badge badge-blue font-semibold">Whois Live</span>
    </div>

    @if (!empty($whois))
        <div class="space-y-5">
            <div class="flex items-center gap-3.5">
                <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-[#1f6feb]/20 to-[#2ea043]/20 border border-[#388bfd]/40 flex items-center justify-center text-base font-black text-[#58a6ff] shadow-inner">
                    {{ strtoupper(mb_substr($whois['name'] ?? $steamId, 0, 2)) }}
                </span>
                <div class="min-w-0">
                    <div class="text-lg font-bold text-[#e6edf3] truncate">{{ $whois['name'] ?? $steamId }}</div>
                    <div class="text-xs text-[#8b949e] mono flex items-center gap-1.5 mt-0.5">
                        <span>{{ $whois['steamId'] ?? $steamId }}</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @php
                    $stats = [
                        ['trophy', 'Слава', $whois['fame'] ?? null, '#a371f7'],
                        ['coins', 'Деньги', $whois['money'] ?? null, '#3fb950'],
                        ['coins', 'Золото', $whois['gold'] ?? null, '#d29922'],
                        ['skull', 'Убийства', $whois['kills'] ?? null, '#f85149'],
                        ['skull', 'Смерти', $whois['deaths'] ?? null, '#8b949e'],
                        ['crosshair', 'K/D', ($whois['kd'] ?? null) !== null ? number_format($whois['kd'], 2) : null, '#58a6ff'],
                    ];
                @endphp
                @foreach ($stats as $s)
                    <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-3 flex flex-col justify-between">
                        <div class="text-[11px] text-[#8b949e] font-semibold flex items-center gap-1.5 mb-1">
                            <x-icon :name="$s[0]" class="w-3.5 h-3.5 shrink-0" />
                            <span>{{ $s[1] }}</span>
                        </div>
                        <div class="text-lg font-black tabular-nums" style="color: {{ $s[3] }}">
                            {{ $s[2] !== null ? (is_numeric($s[2]) ? number_format((float)$s[2], 0, ',', ' ') : $s[2]) : '—' }}
                        </div>
                    </div>
                @endforeach
            </div>

            @php
                $details = [
                    'Puppet kills (Зомби)' => ['skull', $whois['puppetKills'] ?? null],
                    'Headshot kills (В голову)' => ['crosshair', $whois['headshotKills'] ?? null],
                    'Время в игре' => ['clock', $whois['playtime'] ?? null],
                    'Отряд (Squad)' => ['shield', $whois['squad'] ?? null],
                ];
            @endphp
            <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-4 space-y-2.5">
                @foreach ($details as $label => [$icon, $value])
                    @if ($value !== null && $value !== '')
                        <div class="flex justify-between items-center text-xs sm:text-sm py-1 border-b border-[#21262d] last:border-0">
                            <span class="text-[#8b949e] flex items-center gap-1.5">
                                <x-icon :name="$icon" class="w-3.5 h-3.5 shrink-0" />
                                <span>{{ $label }}</span>
                            </span>
                            <span class="font-bold text-[#e6edf3] mono">{{ $value }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

            @if (!empty($whois['vehicles']))
                <div class="rounded-xl border border-[#30363d] bg-[#0d1117] p-4">
                    <div class="text-xs font-bold text-[#e6edf3] mb-2 flex items-center gap-1.5">
                        <x-icon name="car" class="w-4 h-4 text-amber-400" /> Транспорт игрока ({{ count($whois['vehicles']) }})
                    </div>
                    <div class="space-y-1.5">
                        @foreach ($whois['vehicles'] as $v)
                            <div class="text-xs mono text-[#58a6ff] bg-[#161b22] px-2.5 py-1.5 rounded-lg border border-[#30363d]">{{ $v }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="text-[11px] text-[#8b949e]/80 flex items-start gap-1.5">
                <x-icon name="alert-triangle" class="w-4 h-4 text-amber-400" />
                <span>Статистика сервера обновляется с задержкой до ~1 мин — сразу после выдачи значения могут не меняться.</span>
            </div>
        </div>
    @elseif (!empty($error))
        <div class="bg-[#161b22] border border-[#f85149]/50 rounded-xl p-4 mono text-xs sm:text-sm text-[#f85149] shadow-inner">
            {{ $error }}
        </div>
    @else
        <div class="text-sm text-[#8b949e] text-center py-8">Данные не получены</div>
    @endif

    @if (!empty($response))
        <details class="mt-4 pt-3 border-t border-[#21262d]">
            <summary class="text-xs text-[#8b949e] cursor-pointer hover:text-[#58a6ff] transition-colors font-medium">Показать сырой ответ RCON</summary>
            <div class="bg-[#0d1117] border border-[#30363d] rounded-xl p-3.5 mono text-xs text-[#8b949e] whitespace-pre-wrap max-h-56 overflow-y-auto mt-2">{{ $response }}</div>
        </details>
    @endif
</div>