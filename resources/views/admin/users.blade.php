@extends('layouts.admin')

@section('title', 'User Management')

@section('page-header')
    <div class="max-w-3xl space-y-1 text-left">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
        <h1 class="text-2xl font-black text-slate-900">Kelola Pengguna</h1>
        <p class="text-xs text-slate-500">Manajemen akun, status keaktifan, role hak akses, dan pengaturan kata sandi.</p>
    </div>
@endsection

@section('content')
    <livewire:admin.users-manager />
@endsection
