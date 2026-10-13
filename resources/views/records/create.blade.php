@extends('layouts.master')

@section('title', 'Add '.$dataset->recordLabel())
@section('page-title', 'Add '.$dataset->recordLabel())

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $dataset->name() }}: add {{ $dataset->recordLabel() }}</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('records.store', $dataset->key()) }}" method="POST">
                @csrf

                @include('records._form')

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save
                </button>
                <a href="{{ route('records.index', $dataset->key()) }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
