@extends('layouts.master')

@section('title', 'Add Record')
@section('page-title', 'Add Record')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Add Record</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('records.store') }}" method="POST">
                @csrf

                @include('records._form')

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save
                </button>
                <a href="{{ route('records.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
