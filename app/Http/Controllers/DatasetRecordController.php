<?php

namespace App\Http\Controllers;

use App\Datasets\Dataset;
use App\Datasets\RecordQuery;
use App\Http\Requests\DatasetRecordRequest;
use App\Models\DataExport;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * List / create / edit / delete the records of any dataset. Everything is
 * driven by the dataset definition; lookups go through the tenant-scoped
 * model, so another company's record ids are simply not found (404).
 */
class DatasetRecordController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * Most distinct values offered in a filter dropdown.
     */
    private const MAX_FILTER_OPTIONS = 500;

    public function index(Request $request, Dataset $dataset): View
    {
        $query = RecordQuery::fromInput($dataset, $request->query());

        $records = $query->build()->paginate(self::PER_PAGE)->withQueryString();

        return view('records.index', [
            'dataset' => $dataset,
            'query' => $query,
            'records' => $records,
            'filterOptions' => $this->filterOptions($dataset),
            'recentExports' => $request->user()->can('export records')
                ? DataExport::where('user_id', $request->user()->id)->where('dataset_key', $dataset->key())->latest('id')->limit(5)->get()
                : collect(),
        ]);
    }

    public function create(Dataset $dataset): View
    {
        return view('records.create', ['dataset' => $dataset, 'record' => new ($dataset->modelClass())]);
    }

    public function store(DatasetRecordRequest $request, Dataset $dataset): RedirectResponse
    {
        $record = new ($dataset->modelClass());
        $record->fill($request->safe()->only($dataset->fieldKeys()))->save();

        Audit::log('record.created', $record, ['dataset' => $dataset->key()]);

        return redirect()->route('records.index', $dataset->key())->with('status', ucfirst($dataset->recordLabel()).' created.');
    }

    public function edit(Dataset $dataset, string $record): View
    {
        return view('records.edit', ['dataset' => $dataset, 'record' => $this->find($dataset, $record)]);
    }

    public function update(DatasetRecordRequest $request, Dataset $dataset, string $record): RedirectResponse
    {
        $model = $this->find($dataset, $record);
        $model->update($request->safe()->only($dataset->fieldKeys()));

        // Which fields changed, not their values (no personal data in the log).
        if ($changed = array_keys($model->getChanges())) {
            Audit::log('record.updated', $model, ['dataset' => $dataset->key(), 'fields' => array_values(array_diff($changed, ['updated_at']))]);
        }

        return redirect()->route('records.index', $dataset->key())->with('status', ucfirst($dataset->recordLabel()).' updated.');
    }

    public function destroy(Dataset $dataset, string $record): RedirectResponse
    {
        $model = $this->find($dataset, $record);
        $model->delete();

        Audit::log('record.deleted', $model, ['dataset' => $dataset->key()]);

        return redirect()->route('records.index', $dataset->key())->with('status', ucfirst($dataset->recordLabel()).' deleted.');
    }

    private function find(Dataset $dataset, string $id): Model
    {
        return $dataset->modelClass()::findOrFail($id);
    }

    /**
     * The company's existing values for each filterable field.
     *
     * @return array<string, array<int, string>>
     */
    private function filterOptions(Dataset $dataset): array
    {
        $options = [];

        foreach ($dataset->filterableFields() as $field) {
            $options[$field] = $dataset->modelClass()::query()
                ->whereNotNull($field)
                ->distinct()
                ->orderBy($field)
                ->limit(self::MAX_FILTER_OPTIONS)
                ->pluck($field)
                ->all();
        }

        return $options;
    }
}
