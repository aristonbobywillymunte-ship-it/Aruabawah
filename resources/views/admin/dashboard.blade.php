@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    <!-- Status Header -->
    <div class="flex items-center justify-between border-b border-slate-200 pb-5 text-left">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Sistem Kesehatan Platform</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Dashboard Administrator</h1>
            <p class="text-xs text-slate-500 mt-1">Pantau status konektivitas basis data, server perayap, limit AI, serta notifikasi krisis.</p>
        </div>
    </div>

    @php
        $apifyIssues = \App\Models\ApifyActor::where('status', 'active')
            ->where('last_run_status', 'failed')
            ->get()
            ->filter(fn ($actor) => !\App\Models\ApifyActor::shouldSuppressUiError($actor->last_run_message));
    @endphp
    @if($apifyIssues->isNotEmpty())
        <div class="bg-rose-50 border border-rose-200 rounded-3xl p-5 flex items-start gap-3.5 text-rose-800 text-xs font-semibold">
            <span class="material-symbols-outlined text-rose-600 text-xl shrink-0 mt-0.5">error</span>
            <div class="space-y-1 text-left">
                <strong class="text-sm font-bold block text-rose-900">Kendala Pengambilan Data Media Sosial</strong>
                <p class="leading-relaxed">Proses pengambilan data untuk media sosial <strong>{{ $apifyIssues->pluck('platform')->unique()->implode(', ') }}</strong> sedang ditangguhkan sementara. <span class="font-sans bg-rose-100 px-1.5 py-0.5 rounded text-[11px] block mt-1.5 leading-normal">{{ \App\Models\ApifyActor::friendlyRunMessage($apifyIssues->first()->last_run_message) }}</span></p>
            </div>
        </div>
    @endif

    <!-- Livewire System Health Gadget -->
    <livewire:admin.system-health />
@endsection
