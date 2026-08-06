@extends('layouts.master')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <p>{{ __("Welcome back, :name.", ['name' => auth()->user()->name]) }}</p>

            <p class="mb-2">
                <strong>Role:</strong>
                @forelse (auth()->user()->getRoleNames() as $role)
                    <span class="badge text-bg-primary">{{ $role }}</span>
                @empty
                    <span class="text-secondary">No role assigned</span>
                @endforelse
            </p>

            <p class="mb-0">
                <strong>Permissions:</strong>
                @forelse (auth()->user()->getAllPermissions() as $permission)
                    <span class="badge text-bg-secondary">{{ $permission->name }}</span>
                @empty
                    <span class="text-secondary">None</span>
                @endforelse
            </p>
        </div>
    </div>
@endsection
