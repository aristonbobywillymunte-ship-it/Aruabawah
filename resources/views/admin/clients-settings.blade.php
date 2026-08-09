@extends('layouts.admin')

@section('title', 'Pengaturan Klien')

@section('content')
    <livewire:admin.client-management.client-settings :user="$user" />
@endsection
