<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The current company's audit trail (owner only). Includes platform-admin
 * actions taken on this company, so owners can see them too.
 */
class AuditLogController extends Controller
{
    /**
     * Filter groups offered on the page (action prefixes).
     */
    private const AREAS = [
        'import' => 'Imports',
        'record' => 'Records',
        'export' => 'Exports',
        'template' => 'Templates',
        'team' => 'Team',
        'company' => 'Company settings',
        'platform' => 'Platform admin',
    ];

    public function __invoke(Request $request, CurrentCompany $currentCompany): View
    {
        $area = array_key_exists($request->query('area'), self::AREAS) ? $request->query('area') : null;

        $logs = ActivityLog::where('company_id', $currentCompany->id())
            ->when($area, fn ($query) => $query->where('action', 'like', $area.'.%'))
            ->with('actor:id,name,email')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('company.audit', ['logs' => $logs, 'areas' => self::AREAS, 'area' => $area]);
    }
}
