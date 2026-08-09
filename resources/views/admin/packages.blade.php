@extends('layouts.admin')

@section('title', 'Manajemen Paket')

@section('page-header')
    <div class="max-w-3xl space-y-1 text-left">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
        <h1 class="text-2xl font-black text-slate-900">Manajemen Paket</h1>
        <p class="text-xs text-slate-500">Kelola paket layanan, actor Apify, batas penggunaan, dan konfigurasi biaya.</p>
    </div>
@endsection

@section('content')
    <livewire:admin.package-manager />
@endsection
