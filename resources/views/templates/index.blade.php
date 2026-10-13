@extends('layouts.master')

@section('title', 'Import Templates')
@section('page-title', 'Import Templates')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">Saved column mappings</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary">
                Templates are saved from the mapping screen of an import. They match columns by heading name,
                so they keep working when a file's columns are in a different order.
            </p>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            @forelse ($templates as $template)
                @php $dataset = $datasets[$template->dataset_key] ?? null; @endphp
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $template->name }}</h5>
                            <div class="small text-secondary">
                                {{ $dataset?->name() ?? $template->dataset_key }}
                                &middot; {{ $template->duplicate_policy->label() }}
                                &middot; by {{ $template->creator?->name ?? '—' }}, {{ $template->updated_at->diffForHumans() }}
                                @if ($dataset && $template->isOutdatedFor($dataset))
                                    <span class="badge text-bg-warning ms-1">saved for an older field list</span>
                                @endif
                            </div>
                        </div>

                        @can('manage templates')
                            <form method="POST" action="{{ route('templates.destroy', $template) }}"
                                  data-confirm="Delete the template &quot;{{ $template->name }}&quot;?"
                                  onsubmit="return confirm(this.dataset.confirm);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        @endcan
                    </div>

                    <table class="table table-sm mt-2 mb-2" style="max-width: 36rem;">
                        <tbody>
                            @foreach ($template->mapping as $fieldKey => $header)
                                <tr>
                                    <td class="text-secondary" style="width: 40%">{{ $dataset?->field($fieldKey)?->label ?? $fieldKey }}</td>
                                    <td>&larr; column "{{ $header }}"</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @can('manage templates')
                        <form method="POST" action="{{ route('templates.update', $template) }}" class="row g-2 align-items-end">
                            @csrf
                            @method('PATCH')
                            <div class="col-sm-5">
                                <label class="form-label small mb-0" for="name-{{ $template->id }}">Name</label>
                                <input id="name-{{ $template->id }}" type="text" name="name" value="{{ $template->name }}"
                                       maxlength="100" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label small mb-0" for="policy-{{ $template->id }}">When a record already exists</label>
                                <select id="policy-{{ $template->id }}" name="duplicate_policy" class="form-select form-select-sm">
                                    @foreach ($policies as $policy)
                                        <option value="{{ $policy->value }}" @selected($template->duplicate_policy === $policy)>{{ $policy->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">Save</button>
                            </div>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-secondary mb-0">
                    No templates yet. When you confirm an import, tick "Save this mapping as a template".
                </p>
            @endforelse
        </div>
    </div>
@endsection
