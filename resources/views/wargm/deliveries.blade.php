@extends('layouts.app')

@section('title', 'WARGM: Выдачи игрока ' . $steamId)

@section('header-actions')
    <a href="{{ route('wargm.index') }}" class="btn btn-sm flex items-center gap-1.5"><x-icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" /><span>WARGM</span></a>
@endsection

@section('content')
    @include('wargm.deliveries-fragment')
@endsection