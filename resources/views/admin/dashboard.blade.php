@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('page-header')
    <!-- Status Header -->
    <div class="flex items-center justify-between text-left">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Sistem Kesehatan Platform</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Dashboard Administrator</h1>
            <p class="text-xs text-slate-500 mt-1">Pantau status konektivitas basis data, server perayap, limit AI, serta notifikasi krisis.</p>
        </div>
    </div>
@endsection

@section('content')

    @php
        $apifyIssues = \App\Models\ApifyActor::where('status', 'active')
            ->where('last_run_status', 'failed')
            ->get()
            ->filter(fn ($actor) => !\App\Models\ApifyActor::shouldSuppressUiError($actor->last_run_message));
    @endphp
    @if($apifyIssues->isNotEmpty())
        <div x-data="{ show: true }" x-show="show" x-transition class="bg-rose-50 border border-rose-200 rounded-2xl p-4 flex items-start justify-between gap-3 text-rose-800 text-xs font-semibold mb-6">
            <div class="flex items-start gap-3 text-left">
                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5 select-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="space-y-1">
                    <strong class="text-sm font-bold block text-rose-900">Kendala Pengambilan Data Media Sosial</strong>
                    <p class="leading-relaxed">Proses pengambilan data untuk media sosial <strong>{{ $apifyIssues->pluck('platform')->unique()->implode(', ') }}</strong> sedang ditangguhkan sementara. <span class="font-sans bg-rose-100 px-1.5 py-0.5 rounded text-[11px] block mt-1.5 leading-normal">{{ \App\Models\ApifyActor::friendlyRunMessage($apifyIssues->first()->last_run_message) }}</span></p>
                </div>
            </div>
            <button type="button" @click="show = false" class="text-rose-400 hover:text-rose-600 p-1 rounded-lg hover:bg-rose-100 transition cursor-pointer shrink-0" title="Tutup">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    <!-- Livewire System Health Gadget -->
    <livewire:admin.system-health />
@endsection
