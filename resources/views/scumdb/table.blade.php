@extends('layouts.app')

@section('title', 'SCUM.db: ' . $table)

@section('header-actions')
    <a href="{{ route('scumdb.index') }}" class="btn btn-sm flex items-center gap-1.5"><x-icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" /><span>SCUM.db</span></a>
@endsection

@section('content')
    @include('scumdb._nav')

    @php
        $render = function ($v, string $col) {
            if ($v === null) {
                return '<span class="italic text-[#484f58]">NULL</span>';
            }
            if (is_bool($v)) {
                return $v ? 'true' : 'false';
            }
            if (is_float($v)) {
                if (is_nan($v) || is_infinite($v)) {
                    return '<span class="mono text-[#f85149] font-bold">' . e((string) $v) . '</span>';
                }
                if ($v > 100000000 && $v < 10000000000 && preg_match('/time|date/i', $col)) {
                    return '<span title="' . e($v) . '">' . e(date('d.m.Y H:i', (int) $v)) . '</span>';
                }
                return e(rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0');
            }
            if (is_int($v)) {
                if ($v > 100000000 && $v < 10000000000 && preg_match('/time|date/i', $col)) {
                    return '<span title="' . e($v) . '">' . e(date('d.m.Y H:i', (int) $v)) . '</span>';
                }
                return e((string) $v);
            }
            $s = (string) $v;
            if ($s !== '' && (str_contains($s, "\x00") || !mb_check_encoding($s, 'UTF-8'))) {
                $hex = strtoupper(bin2hex(substr($s, 0, 16)));
                $title = 'hex: ' . $hex . (strlen($s) > 16 ? '…' : '');
                return '<span class="mono text-[#a371f7] cursor-help" title="' . e($title) . '">BLOB ' . strlen($s) . ' b</span>';
            }
            $len = mb_strlen($s);
            if ($len > 80) {
                return '<span class="cursor-help" title="' . e($s) . '">' . e(mb_substr($s, 0, 80)) . '…</span>';
            }
            return e($s);
        };
        $pages = $maxPage;
        $fromRow = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
        $toRow = min($total, $page * $perPage);
        $canEdit = $hasPk || $hasRowId;
    @endphp

    <div class="panel overflow-hidden" x-data="scumTableEditor(@js($cfg))">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 px-5 py-4 bg-[#0d1117] border-b border-[#30363d]">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-lg bg-[#58a6ff]/15 border border-[#388bfd]/40 flex items-center justify-center"><x-icon name="database" class="w-4 h-4 text-[#58a6ff]" /></span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[#e6edf3] leading-tight mono truncate">{{ $table }}</div>
                    <div class="text-[11px] text-[#8b949e]">
                        {{ count($columns) }} колонок · {{ number_format($total, 0, ',', ' ') }} строк
                        @if ($q) · фильтр: <span class="mono text-[#58a6ff]">{{ $q }}</span> @endif
                        @if ($hasPk) · <span class="inline-flex items-center gap-1"><x-icon name="edit" class="w-3 h-3 text-[#58a6ff]" /> редактирование включено</span>
                        @elseif ($hasRowId) · <span class="text-[#d29922]">нет первичного ключа — строки по rowid</span>
                        @else · <span class="text-[#d29922]">нет идентификатора — строки только просмотр</span> @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 w-full lg:w-auto">
                <form method="GET" action="{{ route('scumdb.table', $table) }}" class="flex items-center gap-2 flex-1 lg:flex-none">
                    <input type="hidden" name="page" value="1">
                    <input type="search" name="q" value="{{ $q }}" placeholder="Поиск по колонкам…"
                           class="input w-full lg:w-72 mono text-xs">
                    <button type="submit" class="btn btn-sm"><x-icon name="search" class="w-3.5 h-3.5 text-[#8b949e]" /></button>
                    @if ($q)
                        <a href="{{ route('scumdb.table', $table) }}" class="btn btn-sm"><x-icon name="x" class="w-3.5 h-3.5" /></a>
                    @endif
                </form>
                @if ($available && !empty($columns))
                    <button type="button" class="btn btn-sm btn-primary shrink-0 inline-flex items-center gap-1.5" @click="openAdd()"><x-icon name="plus" class="w-3.5 h-3.5" /> Добавить</button>
                @endif
            </div>
        </div>

        @if ($hasPending)
            <div class="flex items-center gap-2 px-5 py-3 bg-[#d29922]/10 border-b border-[#d29922]/40 text-[12px] text-[#e3b341]">
                <x-icon name="alert-triangle" class="w-4 h-4 text-amber-400" />
                <span>В локальной SCUM.db есть невыгруженные правки. Загрузка на сервер заблокирована
                (файл занят игровым процессом) — они уйдут на сервер при остановке/перезапуске сервера.
                Пока метка активна, правки защищены от перезаписи и вносятся в актуальную базу.</span>
            </div>
        @endif

        @if (!$available)
            <div class="text-sm text-[#d29922] px-5 py-4">SCUM.db недоступен.</div>
        @elseif (empty($columns))
            <div class="text-sm text-[#8b949e] text-center py-8">Таблица не найдена или пуста.</div>
        @else
            <div class="flex items-center justify-between gap-3 px-5 py-2.5 border-b border-[#30363d] text-xs text-[#8b949e]">
                <span>Показано {{ number_format($fromRow, 0, ',', ' ') }}–{{ number_format($toRow, 0, ',', ' ') }}</span>
                <div class="flex items-center gap-1.5">
                    @if ($page > 1)
                        <a href="{{ route('scumdb.table', ['table' => $table, 'q' => $q ?: null, 'page' => $page - 1]) }}" class="btn btn-xs flex items-center justify-center p-1" title="Предыдущая страница"><x-icon name="arrow-right" class="w-3 h-3 rotate-180" /></a>
                    @else
                        <span class="btn btn-xs opacity-40 pointer-events-none flex items-center justify-center p-1"><x-icon name="arrow-right" class="w-3 h-3 rotate-180" /></span>
                    @endif
                    <span class="px-2 mono">стр. {{ $page }} / {{ $pages }}</span>
                    @if ($page < $pages)
                        <a href="{{ route('scumdb.table', ['table' => $table, 'q' => $q ?: null, 'page' => $page + 1]) }}" class="btn btn-xs flex items-center justify-center p-1" title="Следующая страница"><x-icon name="arrow-right" class="w-3 h-3" /></a>
                    @else
                        <span class="btn btn-xs opacity-40 pointer-events-none flex items-center justify-center p-1"><x-icon name="arrow-right" class="w-3 h-3" /></span>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto max-h-[720px] custom-scrollbar border border-[#21262d] rounded-2xl shadow-sm">
                <table class="table min-w-full">
                    <thead class="sticky top-0 z-10 bg-[#0c1017]">
                        <tr class="bg-[#0c1017] border-b border-[#21262d]">
                            @if ($canEdit)
                                <th class="w-20"><span class="uppercase tracking-wider text-[10px] text-[#8b949e]">Действия</span></th>
                            @endif
                            <th class="w-14"><span class="uppercase tracking-wider text-[10px] text-[#8b949e]">#</span></th>
                            @foreach ($columns as $c)
                                <th><span class="uppercase tracking-wider text-[10px] text-[#8b949e] mono">{{ $c['name'] }}</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr class="group">
                                @if ($canEdit)
                                    <td class="whitespace-nowrap">
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="btn btn-xs" title="Редактировать"
                                                    @click="openEdit(rows[{{ $i }}])"><x-icon name="edit" class="w-3.5 h-3.5 text-[#58a6ff]" /></button>
                                            <button type="button" class="btn btn-xs btn-danger" title="Удалить"
                                                    @click="remove(rows[{{ $i }}])"><x-icon name="trash" class="w-3.5 h-3.5 text-[#f85149]" /></button>
                                        </div>
                                    </td>
                                @endif
                                <td class="text-xs text-[#8b949e] tabular-nums whitespace-nowrap">{{ $fromRow + $i }}</td>
                                @foreach ($columns as $c)
                                    <td class="align-top max-w-[24rem]">{!! $render($row[$c['name']] ?? null, $c['name']) !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (empty($rows))
                <div class="text-sm text-[#8b949e] text-center py-8">
                    @if ($q)
                        По запросу «{{ $q }}» ничего не найдено.
                    @else
                        В таблице нет строк.
                    @endif
                </div>
            @endif
        @endif

        <!-- Модалка редактирования/добавления строки -->
        <template x-if="open">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4" x-cloak>
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="close()"></div>
                <div class="relative w-full max-w-2xl flex flex-col rounded-xl border border-[#30363d] bg-[#161b22] shadow-2xl overflow-hidden max-h-[90vh]">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-[#30363d]">
                        <div class="font-bold text-[#e6edf3]" x-text="title()">…</div>
                        <button type="button" class="btn btn-sm" @click="close()"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                    </div>
                    <div class="p-5 space-y-3 overflow-y-auto">
                        <div x-show="notice" x-cloak class="rounded-lg border px-4 py-3 text-sm"
                             :class="notice && notice.ok ? 'border-[#2ea043]/50 bg-[#2ea043]/10 text-[#3fb950]' : 'border-[#f85149]/50 bg-[#f85149]/10 text-[#f85149]'">
                            <span x-text="notice && notice.text"></span>
                        </div>
                        <template x-for="c in cols" :key="c.name">
                            <div>
                                <label class="flex items-center gap-2 text-xs text-[#8b949e] mb-1.5">
                                    <span class="mono text-[#e6edf3] font-semibold" x-text="c.name"></span>
                                    <template x-if="isPk(c)"><span class="badge badge-blue">PK</span></template>
                                    <template x-if="isBlob(c)"><span class="badge badge-gray">BLOB</span></template>
                                    <template x-if="c.readonly && !isBlob(c)"><span class="badge badge-gray">только просмотр</span></template>
                                </label>
                                <template x-if="c.readonly">
                                    <div class="input mono text-xs text-[#8b949e] whitespace-pre-wrap break-all cursor-help"
                                         :title="values[c.name] || 'NULL'">
                                        <span x-text="values[c.name] ? values[c.name] : 'NULL'"></span>
                                    </div>
                                </template>
                                <template x-if="!c.readonly">
                                    <div class="flex flex-col gap-1">
                                        <input x-show="isNum(c)" type="number" step="any"
                                               x-model="values[c.name]" class="input mono text-sm">
                                        <textarea x-show="!isNum(c)" rows="2" x-model="values[c.name]" class="input mono text-sm"></textarea>
                                    </div>
                                </template>
                                <div class="text-[10px] text-[#484f58] mt-0.5" x-text="fieldHint(c)"></div>
                            </div>
                        </template>
                    </div>
                    <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-[#30363d] bg-[#0d1117]">
                        <button type="button" class="btn btn-sm" @click="close()">Отмена</button>
                        <button type="button" class="btn btn-sm btn-primary" @click="submit()" :disabled="busy"
                                x-text="busy ? 'Сохранение…' : 'Сохранить'"><x-icon name="check" class="w-4 h-4 inline mr-1" /> Сохранить</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endsection
