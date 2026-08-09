@extends('layouts.admin')

@section('title', 'Manajemen Klien')

@section('page-header')
    <div class="max-w-3xl space-y-1 text-left">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
        <h1 class="text-2xl font-black text-slate-900">Manajemen Klien</h1>
        <p class="text-xs text-slate-500">Kelola akun klien, batas limit, dan izin proyek secara real-time.</p>
    </div>
@endsection

@section('content')
    <livewire:admin.client-management.client-list />
@endsection
