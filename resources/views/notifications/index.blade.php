@extends('layouts.master')

@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Your notifications</h3>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Mark all as read</button>
                </form>
            @endif
        </div>
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <a href="{{ route('notifications.open', $notification->id) }}"
                   class="list-group-item list-group-item-action {{ $notification->read_at ? '' : 'fw-semibold' }}">
                    <div class="d-flex justify-content-between">
                        <span>
                            <i class="bi bi-circle-fill text-{{ $notification->data['level'] ?? 'secondary' }} me-1" style="font-size: .5rem" aria-hidden="true"></i>
                            {{ $notification->data['title'] ?? 'Notification' }}
                            @unless ($notification->read_at) <span class="visually-hidden">(unread)</span> @endunless
                        </span>
                        <small class="text-secondary fw-normal">{{ $notification->created_at->diffForHumans() }}</small>
                    </div>
                    <div class="small text-secondary fw-normal">{{ $notification->data['message'] ?? '' }}</div>
                </a>
            @empty
                <div class="list-group-item text-secondary">No notifications yet.</div>
            @endforelse
        </div>
        <div class="card-footer">{{ $notifications->links() }}</div>
    </div>
@endsection
