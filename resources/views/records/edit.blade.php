@extends('layouts.master')

@section('title', 'Edit '.$dataset->recordLabel())
@section('page-title', 'Edit '.$dataset->recordLabel())

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $dataset->name() }}: edit {{ $dataset->recordLabel() }}</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('records.update', [$dataset->key(), $record]) }}" method="POST">
                @csrf
                @method('PUT')

                @include('records._form')

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save
                </button>
                <a href="{{ route('records.index', $dataset->key()) }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
