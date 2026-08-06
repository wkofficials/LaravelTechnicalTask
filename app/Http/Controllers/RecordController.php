<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecordRequest;
use App\Http\Requests\UpdateRecordRequest;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordController extends Controller
{
    /**
     * Columns a user is allowed to sort by. Whitelisted so the `sort` query
     * parameter can never be used to order by an arbitrary column.
     */
    private const SORTABLE_COLUMNS = ['name', 'email', 'phone', 'company', 'city', 'country', 'created_at'];

    /**
     * Columns covered by the FULLTEXT index used for search.
     */
    private const SEARCHABLE_COLUMNS = ['name', 'email', 'company', 'city', 'country'];

    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), self::SORTABLE_COLUMNS, true)
            ? $request->query('sort')
            : 'id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->query('q', ''));

        $query = Record::query();

        if ($search !== '') {
            $this->applySearch($query, $search);
        }

        $records = $query->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(25)
            ->withQueryString();

        return view('records.index', [
            'records' => $records,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
        ]);
    }

    /**
     * MySQL/MariaDB gets a real indexed FULLTEXT boolean-mode search, which
     * stays fast at 100k+ rows. Other drivers (SQLite in tests) fall back to
     * a plain LIKE scan — same behavior, no index, only used outside MySQL.
     */
    private function applySearch(Builder $query, string $search): void
    {
        if ($query->getConnection()->getDriverName() === 'mysql') {
            $booleanSearch = $this->toBooleanSearchExpression($search);

            if ($booleanSearch !== '') {
                $query->whereFullText(self::SEARCHABLE_COLUMNS, $booleanSearch, ['mode' => 'boolean']);
            }

            return;
        }

        $query->where(function (Builder $query) use ($search) {
            foreach (self::SEARCHABLE_COLUMNS as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    /**
     * Turn free-text user input into a MySQL boolean-mode fulltext
     * expression: each word becomes a required prefix match (+word*),
     * with boolean-mode operator characters stripped from the input first.
     */
    private function toBooleanSearchExpression(string $term): string
    {
        $clean = preg_replace('/[+\-<>()~*"@]+/', ' ', $term);
        $words = array_filter(explode(' ', $clean), fn ($word) => $word !== '');

        if ($words === []) {
            return '';
        }

        return implode(' ', array_map(fn ($word) => '+'.$word.'*', $words));
    }

    public function create(): View
    {
        return view('records.create', ['record' => new Record()]);
    }

    public function store(StoreRecordRequest $request): RedirectResponse
    {
        Record::create($request->validated());

        return redirect()->route('records.index')->with('status', 'Record created.');
    }

    public function edit(Record $record): View
    {
        return view('records.edit', ['record' => $record]);
    }

    public function update(UpdateRecordRequest $request, Record $record): RedirectResponse
    {
        $record->update($request->validated());

        return redirect()->route('records.index')->with('status', 'Record updated.');
    }

    public function destroy(Record $record): RedirectResponse
    {
        $record->delete();

        return redirect()->route('records.index')->with('status', 'Record deleted.');
    }
}
