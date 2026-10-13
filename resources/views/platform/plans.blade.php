@extends('layouts.master')

@section('title', 'Plans')
@section('page-title', 'Plans & limits')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-secondary">
        Limits apply immediately to every company on the plan. Lowering a limit doesn't remove anything that already exists;
        it only blocks new imports, invitations or templates above the limit.
    </p>

    <div class="row g-3">
        @foreach ($plans as $plan)
            <div class="col-lg-4">
                <div class="card h-100">
                    <form method="POST" action="{{ route('platform.plans.update', $plan) }}">
                        @csrf
                        @method('PUT')
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <input type="text" name="name" value="{{ $plan->name }}" class="form-control form-control-sm fw-semibold me-2" aria-label="Plan name" required>
                            <span class="text-nowrap small text-secondary">
                                {{ $plan->companies_count }} {{ Str::plural('company', $plan->companies_count) }}
                                @if ($plan->is_default) &middot; default @endif
                            </span>
                        </div>
                        <div class="card-body">
                            @foreach ($limits as $column => $label)
                                <div class="mb-2">
                                    <label class="form-label small mb-0" for="{{ $plan->key }}-{{ $column }}">{{ $label }}</label>
                                    <input type="number" min="0" id="{{ $plan->key }}-{{ $column }}" name="{{ $column }}" value="{{ $plan->$column }}"
                                           @if ($column === 'max_file_size_mb') max="{{ \App\Models\Plan::HARD_MAX_FILE_SIZE_MB }}" @endif
                                           class="form-control form-control-sm" required>
                                </div>
                            @endforeach

                            <hr>
                            <div class="row g-2">
                                <div class="col-5">
                                    <label class="form-label small mb-0" for="{{ $plan->key }}-pm">Price / month</label>
                                    <input type="number" step="0.01" min="0" id="{{ $plan->key }}-pm" name="price_monthly"
                                           value="{{ $plan->price_monthly_cents === null ? '' : number_format($plan->price_monthly_cents / 100, 2, '.', '') }}"
                                           class="form-control form-control-sm" placeholder="not sold">
                                </div>
                                <div class="col-5">
                                    <label class="form-label small mb-0" for="{{ $plan->key }}-py">Price / year</label>
                                    <input type="number" step="0.01" min="0" id="{{ $plan->key }}-py" name="price_yearly"
                                           value="{{ $plan->price_yearly_cents === null ? '' : number_format($plan->price_yearly_cents / 100, 2, '.', '') }}"
                                           class="form-control form-control-sm" placeholder="not sold">
                                </div>
                                <div class="col-2">
                                    <label class="form-label small mb-0" for="{{ $plan->key }}-cur">Cur.</label>
                                    <input type="text" id="{{ $plan->key }}-cur" name="currency" value="{{ $plan->currency }}" maxlength="3"
                                           class="form-control form-control-sm text-uppercase" required>
                                </div>
                            </div>
                            <div class="form-check mt-2">
                                <input type="hidden" name="is_public" value="0">
                                <input class="form-check-input" type="checkbox" name="is_public" value="1" id="{{ $plan->key }}-public" @checked($plan->is_public)>
                                <label class="form-check-label small" for="{{ $plan->key }}-public">Show on the public pricing page</label>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-sm btn-primary">Save {{ $plan->name }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
