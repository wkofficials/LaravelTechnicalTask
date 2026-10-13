<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    /**
     * Switch the user's active company. The target must be an active company
     * the user is a member of; anything else is a 404, so company IDs can't
     * be probed.
     */
    public function switch(Request $request, Company $company): RedirectResponse
    {
        $user = $request->user();

        abort_unless($company->isActive() && $user->belongsToCompany($company), 404);

        $user->switchCompany($company);

        return redirect()->route('dashboard')->with('status', "Switched to {$company->name}.");
    }

    /**
     * Shown to signed-in users who no longer belong to any active company.
     */
    public function none(): View
    {
        return view('companies.none');
    }
}
