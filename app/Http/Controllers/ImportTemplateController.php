<?php

namespace App\Http\Controllers;

use App\Datasets\DatasetRegistry;
use App\Enums\DuplicatePolicy;
use App\Models\ImportTemplate;
use App\Support\Audit;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Saved column mappings of the current company. Templates are created from
 * the mapping screen; here they can be reviewed, renamed and deleted.
 * Route binding is tenant-scoped, so other companies' templates are 404s.
 */
class ImportTemplateController extends Controller
{
    public function index(DatasetRegistry $datasets): View
    {
        return view('templates.index', [
            'templates' => ImportTemplate::with('creator:id,name')->orderBy('dataset_key')->orderBy('name')->get(),
            'datasets' => $datasets->all(),
            'policies' => DuplicatePolicy::cases(),
        ]);
    }

    public function update(Request $request, ImportTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('import_templates', 'name')
                ->where('company_id', app(CurrentCompany::class)->id())
                ->ignore($template)],
            'duplicate_policy' => ['required', Rule::enum(DuplicatePolicy::class)],
        ]);

        $template->update($validated);

        if ($changed = array_diff(array_keys($template->getChanges()), ['updated_at'])) {
            Audit::log('template.updated', $template, ['fields' => array_values($changed)]);
        }

        return back()->with('status', "Template \"{$template->name}\" saved.");
    }

    public function destroy(ImportTemplate $template): RedirectResponse
    {
        $template->delete();

        Audit::log('template.deleted', $template, ['name' => $template->name]);

        return back()->with('status', "Template \"{$template->name}\" deleted.");
    }
}
