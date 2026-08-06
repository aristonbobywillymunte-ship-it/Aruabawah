@extends('layouts.admin')

@section('title', 'Pipeline Monitor')

@section('page-header')
    <!-- Status Header -->
    <div class="flex items-center justify-between text-left">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Sistem Kesehatan Platform</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Pipeline Monitor</h1>
            <p class="text-xs text-slate-500 mt-1">Pantau scraping, analisis AI, dan notifikasi secara real-time</p>
        </div>
    </div>
@endsection

@section('content')
    <livewire:admin.pipeline-monitor />
@endsection

