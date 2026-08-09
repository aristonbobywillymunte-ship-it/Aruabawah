@extends('layouts.admin')

@section('title', 'Database Management')

@section('page-header')
    <div class="max-w-3xl space-y-1 text-left">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
        <h1 class="text-2xl font-black text-slate-900">Manajemen Database</h1>
        <p class="text-xs text-slate-500">Ekspor berkas cadangan atau pulihkan seluruh data sistem secara instan.</p>
    </div>
@endsection

@section('content')
    <livewire:admin.database-management />
@endsection
