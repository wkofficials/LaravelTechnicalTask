@extends('layouts.master')

@section('title', 'Edit Record')
@section('page-title', 'Edit Record')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit Record</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('records.update', $record) }}" method="POST">
                @csrf
                @method('PUT')

                @include('records._form')

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save
                </button>
                <a href="{{ route('records.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
