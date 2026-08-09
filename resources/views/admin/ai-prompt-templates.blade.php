@extends('layouts.admin')

@section('title', 'AI Prompt Templates')

@section('page-header')
    <div class="flex items-center justify-between text-left">
        <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">AI Prompt Templates</h1>
            <p class="text-xs text-slate-500 mt-1">Atur prompt utama, user prompt template, dan schema output untuk seluruh alur AI.</p>
        </div>
    </div>
@endsection

@section('content')
    <livewire:admin.ai-prompt-templates />
@endsection
