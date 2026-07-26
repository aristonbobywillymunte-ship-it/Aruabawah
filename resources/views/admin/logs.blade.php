@extends('layouts.admin')

@section('title', 'System Logs')

@section('content')
    <livewire:admin.system-logs :file="request('file')" :source="request('source')" />
@endsection
