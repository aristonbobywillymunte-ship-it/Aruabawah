@extends('layouts.admin')

@section('title', 'System Logs')

@section('page-header')
    <div class="max-w-3xl space-y-1 text-left">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
        <h1 class="text-2xl font-black text-slate-900">Log & Aktivitas Sistem</h1>
        <p class="text-xs text-slate-500">Pantau catatan eksekusi perayapan portal berita, scraping Apify, dan error scheduler.</p>
    </div>
@endsection

@section('content')
    <livewire:admin.system-logs :file="request('file')" :source="request('source')" />
@endsection
