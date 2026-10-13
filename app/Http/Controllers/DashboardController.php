<?php

namespace App\Http\Controllers;

use App\Enums\ImportBatchStatus;
use App\Models\CompanyInvitation;
use App\Models\ImportBatch;
use App\Models\ImportTemplate;
use App\Models\Record;
use App\Models\User;
use App\Support\PlanLimits;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Company overview: records, this month's imports, recent imports and
     * usage. Each part only shows what the user's permissions allow.
     */
    public function index(Request $request, CurrentCompany $currentCompany): View
    {
        $user = $request->user();
        $limits = PlanLimits::for($currentCompany->get());
        $data = ['recordCount' => $user->can('view records') ? Record::count() : null];

        if ($user->can('import records')) {
            $monthStart = now($currentCompany->get()->timezone ?: 'UTC')->startOfMonth()->utc();
            $visible = ImportBatch::visibleTo($user);

            $byStatus = (clone $visible)->where('created_at', '>=', $monthStart)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $data['importsThisMonth'] = $byStatus->sum();
            $data['failedThisMonth'] = (int) ($byStatus[ImportBatchStatus::Failed->value] ?? 0);
            $data['withErrorsThisMonth'] = (int) ($byStatus[ImportBatchStatus::CompletedWithErrors->value] ?? 0);
            $data['recentImports'] = (clone $visible)->with('user:id,name')->latest('id')->limit(5)->get();
        }

        if ($user->can('view usage')) {
            $data['rowsUsed'] = $limits->importRowsUsed();
            $data['rowsLimit'] = $limits->plan()->monthly_import_rows;
            $data['planName'] = $limits->plan()->name;
        }

        $data['onboarding'] = $this->onboardingSteps($user, $currentCompany);
        $data['company'] = $currentCompany->get();
        $data['role'] = $currentCompany->get()->roleOf($user);
        $data['teamCount'] = $currentCompany->get()->users()->count();
        $data['localHour'] = (int) now($currentCompany->get()->timezone ?: 'UTC')->format('G');

        return view('dashboard.index', $data);
    }

    /**
     * "Getting started" checklist for people who can import. Hidden once
     * every step that applies to the user is done.
     *
     * @return array<int, array{label: string, done: bool, url: string, action: string}>|null
     */
    private function onboardingSteps(User $user, CurrentCompany $currentCompany): ?array
    {
        if (! $user->can('import records')) {
            return null;
        }

        $company = $currentCompany->get();
        $steps = [
            ['label' => 'Download the sample file to see the expected columns', 'done' => ImportBatch::exists(), 'url' => route('imports.sample', 'contacts'), 'action' => 'Download'],
            ['label' => 'Import your first file', 'done' => ImportBatch::whereIn('status', [ImportBatchStatus::Completed->value, ImportBatchStatus::CompletedWithErrors->value])->exists(), 'url' => route('imports.create'), 'action' => 'Import'],
        ];

        if ($user->can('manage templates')) {
            $steps[] = ['label' => 'Save a column template for files you receive regularly', 'done' => ImportTemplate::exists(), 'url' => route('templates.index'), 'action' => 'How'];
        }

        if ($user->can('manage team')) {
            $steps[] = ['label' => 'Invite a teammate', 'done' => $company->users()->count() > 1 || CompanyInvitation::exists(), 'url' => route('team.index'), 'action' => 'Invite'];
        }

        return collect($steps)->every('done') ? null : $steps;
    }
}
