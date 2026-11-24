@extends('layouts.app')

@section('title', 'Плагин: Телепорт')

@section('content')
    <div x-data="{
        locations: @js($locations ?? []),
        newName: '',
        newX: '',
        newY: '',
        newZ: '0',
        pasteString: '',

        addLocation() {
            if (!this.newName.trim() || this.newX === '' || this.newY === '') {
                alert('Укажите название и координаты X, Y');
                return;
            }
            this.locations.push({
                name: this.newName.trim(),
                x: parseFloat(this.newX) || 0,
                y: parseFloat(this.newY) || 0,
                z: parseFloat(this.newZ) || 0
            });
            this.newName = '';
            this.newX = '';
            this.newY = '';
            this.newZ = '0';
            if (window.toast) window.toast('Точка телепорта добавлена', 'success');
        },

        removeLocation(index) {
            if (!confirm('Удалить эту точку телепорта?')) return;
            this.locations.splice(index, 1);
            if (window.toast) window.toast('Точка удалена', 'info');
        },

        parsePasteLocation() {
            const str = this.pasteString.trim();
            if (!str) return;

            // Формат SCUM: #Location X=12345.6 Y=-67890.1 Z=123.4
            const xMatch = str.match(/X=([-\d.]+)/i) || str.match(/([-\d.]+)[,\s]+([-\d.]+)/);
            const yMatch = str.match(/Y=([-\d.]+)/i);
            const zMatch = str.match(/Z=([-\d.]+)/i);

            if (xMatch && yMatch) {
                this.newX = xMatch[1];
                this.newY = yMatch[1];
                this.newZ = zMatch ? zMatch[1] : '0';
                if (!this.newName) this.newName = 'Точка ' + (this.locations.length + 1);
                this.pasteString = '';
                if (window.toast) window.toast('Координаты успешно извлечены!', 'success');
            } else {
                alert('Не удалось распознать координаты. Вставьте строку вида: X=12345.6 Y=-67890.1 Z=123.4');
            }
        },

        copyCommand(loc) {
            const cmd = `#teleport ${Math.round(loc.x)} ${Math.round(loc.y)} ${Math.round(loc.z)}`;
            navigator.clipboard.writeText(cmd);
            if (window.toast) window.toast(`Скопировано: ${cmd}`, 'success');
        }
    }" class="space-y-6">

        {{-- ===== Hero Banner ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] p-5 md:p-7 shadow-xl">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-[#1f6feb]/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-28 -left-16 w-72 h-72 rounded-full bg-[#a371f7]/15 blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gradient-to-br from-[#1f6feb]/30 to-[#a371f7]/20 border border-[#1f6feb]/40 flex items-center justify-center text-[#a371f7] shadow-lg shadow-[#1f6feb]/10 shrink-0"><x-icon name="compass" class="w-7 h-7" /></div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl md:text-2xl font-bold text-[#e6edf3] tracking-tight">Плагин: Телепорт</h1>
                            <span class="badge badge-blue">Чат-команда !teleport</span>
                        </div>
                        <p class="text-xs md:text-sm text-[#8b949e] mt-1">
                            Управление публичными точками телепортации на сервере для игроков и администрации.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 md:gap-3 flex-wrap text-xs">
                    <div class="px-3 py-2 rounded-xl bg-[#161b22]/80 border border-[#30363d] flex items-center gap-2">
                        <span class="text-[#8b949e]">Всего точек:</span>
                        <span class="font-bold text-[#58a6ff]" x-text="locations.length">{{ count($locations) }}</span>
                    </div>
                    <a href="{{ route('map.index') }}" class="btn btn-sm flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="map" class="w-4 h-4 text-emerald-400" /> Открыть карту</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ===== Форма добавления точки & Быстрая вставка ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- 1. Добавление новой локации --}}
            <div class="panel p-5 md:p-6 lg:col-span-5 rounded-2xl flex flex-col justify-between space-y-4">
                <div>
                    <h2 class="font-bold text-base text-[#e6edf3] mb-1 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="plus" class="w-4 h-4 text-sky-400" /> Добавить новую точку</span>
                    </h2>
                    <p class="text-xs text-[#8b949e] mb-4">
                        Укажите название точки и координаты или вставьте результат внутриигровой команды <code class="mono text-[#58a6ff]">#location</code>.
                    </p>

                    {{-- Быстрая вставка #location --}}
                    <div class="p-3 rounded-xl border border-[#30363d] bg-[#0d1117] mb-4 space-y-2">
                        <label class="block text-[11px] font-semibold text-[#8b949e]">Быстрая вставка из SCUM (#location):</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="pasteString" placeholder="X=12345.6 Y=-67890.1 Z=123.4"
                                   class="input mono text-xs py-1.5 bg-[#161b22] border-[#30363d] focus:border-[#58a6ff] flex-1">
                            <button type="button" @click="parsePasteLocation()" class="btn btn-xs btn-accent shrink-0">
                                Извлечь
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#8b949e] mb-1">Название точки</label>
                            <input type="text" x-model="newName" placeholder="например, Склад A1 или Торговая зона"
                                   class="input text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">X</label>
                                <input type="number" step="any" x-model="newX" placeholder="X"
                                       class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Y</label>
                                <input type="number" step="any" x-model="newY" placeholder="Y"
                                       class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#8b949e] mb-1">Z</label>
                                <input type="number" step="any" x-model="newZ" placeholder="Z"
                                       class="input mono text-xs py-2 bg-[#0d1117] border-[#30363d] focus:border-[#58a6ff]">
                            </div>
                        </div>

                        <button type="button" @click="addLocation()" class="btn btn-accent w-full py-2 font-bold mt-2 flex items-center justify-center gap-1.5 shadow-md shadow-[#1f6feb]/20">
                            <x-icon name="plus" class="w-4 h-4" />
                            <span>Добавить в список</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- 2. Список сохранённых локаций --}}
            <div class="panel p-5 md:p-6 lg:col-span-7 rounded-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <h2 class="font-bold text-base text-[#e6edf3] flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5"><x-icon name="map" class="w-4 h-4 text-emerald-400" /> Сохранённые локации</span>
                        </h2>
                        <span class="badge badge-gray mono text-xs" x-text="locations.length + ' точек'"></span>
                    </div>

                    <template x-if="locations.length === 0">
                        <div class="text-center py-12 px-4 rounded-xl border border-[#21262d] bg-[#0c1017]/80">
                            <div class="w-12 h-12 rounded-2xl bg-[#161b22] border border-[#21262d] flex items-center justify-center mx-auto mb-3 text-[#8b949e]">
                                <x-icon name="compass" class="w-6 h-6 opacity-60 text-[#58a6ff]" />
                            </div>
                            <div class="text-sm font-semibold text-[#e6edf3]">Точек телепорта пока нет</div>
                            <div class="text-xs text-[#8b949e] mt-1 max-w-sm mx-auto">
                                Добавьте первую точку вручную слева или вставьте координаты из игры.
                            </div>
                        </div>
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[460px] overflow-y-auto pr-1">
                        <template x-for="(loc, idx) in locations" :key="idx">
                            <div class="p-3.5 rounded-xl border border-[#30363d] bg-[#0d1117] hover:border-[#58a6ff] transition-all flex flex-col justify-between gap-2.5 group">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs text-[#e6edf3] truncate group-hover:text-[#58a6ff] transition-colors" x-text="loc.name"></div>
                                        <div class="mono text-[11px] text-[#8b949e] mt-0.5" x-text="`X: ${Math.round(loc.x)} | Y: ${Math.round(loc.y)} | Z: ${Math.round(loc.z || 0)}`"></div>
                                    </div>
                                    <button type="button" @click="removeLocation(idx)" class="text-[#8b949e] hover:text-[#f85149] p-1 rounded hover:bg-[#3a1a1a]" title="Удалить точку"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                                </div>

                                <div class="pt-2 border-t border-[#21262d] flex items-center justify-between gap-2 text-xs">
                                    <button type="button" @click="copyCommand(loc)" class="btn btn-xs flex items-center gap-1" title="Скопировать RCON команду">
                                        <span class="inline-flex items-center gap-1.5"><x-icon name="terminal" class="w-3.5 h-3.5 text-[#58a6ff]" /> #teleport</span>
                                    </button>
                                    <a :href="`{{ route('map.index') }}?x=${loc.x}&y=${loc.y}`" class="text-[11px] text-[#58a6ff] hover:underline flex items-center gap-1">
                                        <span class="inline-flex items-center gap-1.5"><x-icon name="map" class="w-3.5 h-3.5 text-emerald-400" /> На карте</span>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Форма сохранения на сервер --}}
                <form action="{{ route('plugins.teleport.save') }}" method="POST" class="mt-4 pt-3 border-t border-[#21262d] flex items-center justify-end">
                    @csrf
                    <input type="hidden" name="locations_json" :value="JSON.stringify(locations)">
                    <button type="submit" class="btn btn-primary font-bold py-2 px-6 shadow-md shadow-[#238636]/20">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" /> Сохранить изменения на сервере</span>
                    </button>
                </form>
            </div>

        </div>

    </div>
@endsection