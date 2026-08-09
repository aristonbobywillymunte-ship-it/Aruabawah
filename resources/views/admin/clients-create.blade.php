@extends('layouts.admin')

@section('title', 'Tambah Klien Baru')

@section('page-header')
    <div class="max-w-3xl space-y-1 text-left">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
        <h1 class="text-2xl font-black text-slate-900">Tambah Klien Baru</h1>
        <p class="text-xs text-slate-500">Buat akun untuk klien Anda agar bisa mengelola proyek.</p>
    </div>
@endsection

@section('content')
    <livewire:admin.client-management.client-create />
@endsection
