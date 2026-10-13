<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Record;
use App\Support\PlanLimits;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\View\View;

class UsageController extends Controller
{
    public function __invoke(CurrentCompany $currentCompany): View
    {
        $limits = PlanLimits::for($currentCompany->get());
        $plan = $limits->plan();

        return view('company.usage', [
            'plan' => $plan,
            'period' => $limits->period(),
            'meters' => [
                ['label' => Plan::LIMITS['monthly_import_rows'], 'used' => $limits->importRowsUsed(), 'limit' => $plan->monthly_import_rows],
                ['label' => Plan::LIMITS['max_users'], 'used' => $limits->seatsUsed(), 'limit' => $plan->max_users],
                ['label' => Plan::LIMITS['max_templates'], 'used' => $limits->templatesUsed(), 'limit' => $plan->max_templates],
            ],
            'perImport' => [
                Plan::LIMITS['max_rows_per_import'] => number_format($plan->max_rows_per_import),
                Plan::LIMITS['max_file_size_mb'] => intdiv($limits->maxFileSizeKb(), 1024).' MB',
            ],
            'recordCount' => Record::count(),
        ]);
    }
}
