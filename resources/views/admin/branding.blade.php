@extends('layouts.admin')

@section('title', 'Application Branding')

@section('page-header')
    <div class="flex items-center justify-between text-left">
        <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Branding Aplikasi</h1>
            <p class="text-xs text-slate-500 mt-1">Kustomisasi nama dan logo aplikasi secara dinamis.</p>
        </div>
    </div>
@endsection

@section('content')
    <livewire:admin.branding-manager />
@endsection
