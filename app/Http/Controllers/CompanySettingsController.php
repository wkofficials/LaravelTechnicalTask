<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Tenancy\CurrentCompany;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings of the current company (owner only). Plan and status are not
 * editable here — those belong to the platform admin.
 */
class CompanySettingsController extends Controller
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function edit(): View
    {
        return view('company.settings', [
            'company' => $this->currentCompany->get(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->currentCompany->get();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'email_import_notifications' => ['boolean'],
        ]);

        $company->forceFill([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'email_import_notifications' => $request->boolean('email_import_notifications'),
        ])->save();

        if ($changed = array_diff(array_keys($company->getChanges()), ['updated_at'])) {
            Audit::log('company.settings_updated', $company, ['fields' => array_values($changed)]);
        }

        return back()->with('status', 'Company settings saved.');
    }
}
