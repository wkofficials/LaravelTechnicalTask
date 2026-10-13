@extends('layouts.master')

@section('title', 'Team')
@section('page-title', 'Team — '.$company->name)

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">Members</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    @php
                                        $user = $member['user'];
                                        $editable = ! $user->is(auth()->user()) && $member['role'] !== 'owner';
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $user->name }}
                                            @if ($user->is(auth()->user()))
                                                <span class="badge text-bg-secondary">You</span>
                                            @endif
                                        </td>
                                        <td>{{ $user->email }}</td>
                                        <td>
                                            @if ($editable)
                                                <form method="POST" action="{{ route('team.members.update', $user) }}" class="d-flex gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="role" class="form-select form-select-sm" style="max-width: 10rem;" aria-label="Role for {{ $user->name }}">
                                                        @foreach ($assignableRoles as $role)
                                                            <option value="{{ $role }}" @selected($member['role'] === $role)>{{ ucfirst($role) }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                                </form>
                                            @else
                                                <span class="badge text-bg-primary">{{ ucfirst($member['role'] ?? 'none') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($editable)
                                                <form method="POST" action="{{ route('team.members.remove', $user) }}"
                                                      data-confirm="Remove {{ $user->name }} from {{ $company->name }}?"
                                                      onsubmit="return confirm(this.dataset.confirm);">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-person-dash"></i> Remove
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Pending invitations</h3>
                </div>
                <div class="card-body p-0">
                    @if ($invitations->isEmpty())
                        <p class="text-secondary p-3 mb-0">No pending invitations.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Invited by</th>
                                        <th>Expires</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invitations as $invitation)
                                        <tr>
                                            <td>{{ $invitation->email }}</td>
                                            <td>{{ ucfirst($invitation->role) }}</td>
                                            <td>{{ $invitation->inviter?->name ?? '—' }}</td>
                                            <td>{{ $invitation->expires_at->diffForHumans() }}</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('team.invitations.revoke', $invitation) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Revoke</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Invite a team member</h3>
                </div>
                <div class="px-3 pt-3 small text-secondary">
                    {{ $seatsUsed }} of {{ $seatLimit }} users on your plan (members + pending invitations).
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('team.invite') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                                @foreach ($assignableRoles as $role)
                                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-envelope"></i> Send invitation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
