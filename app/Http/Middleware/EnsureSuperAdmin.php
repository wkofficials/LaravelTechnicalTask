<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the platform admin area. Platform pages work across companies, so
 * the tenant context is cleared: nothing there should silently run inside
 * whichever company the admin happens to belong to. Platform code only
 * reads aggregates (counts), never tenant records.
 */
class EnsureSuperAdmin
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin, 404);

        $this->currentCompany->clear();

        return $next($request);
    }
}
