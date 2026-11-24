@extends('layouts.app')

@section('title', 'Whois: ' . $steamId)

@section('content')
    <div class="relative overflow-hidden rounded-2xl border border-[#30363d] bg-gradient-to-br from-[#101623] via-[#0d1117] to-[#0a0e14] mb-6 p-6">
        <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-[#1f6feb]/20 blur-3xl pointer-events-none"></div>
        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-[#1f6feb]/20 border border-[#388bfd]/40 flex items-center justify-center shadow-lg shadow-[#1f6feb]/10"><x-icon name="user" class="w-6 h-6 text-[#58a6ff]" /></div>
                <div>
                    <div class="text-2xl font-bold text-[#e6edf3]">Карточка игрока</div>
                    <div class="text-sm text-[#8b949e] mono">{{ $steamId }}</div>
                </div>
            </div>
            <a href="{{ route('players.index') }}" class="btn shrink-0 flex items-center gap-1.5"><x-icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" /><span>Назад к игрокам</span></a>
        </div>
    </div>
    @include('players.whois-fragment')
@endsection