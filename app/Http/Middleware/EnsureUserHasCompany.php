<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards company workspace routes: the user must have an active company.
 */
class EnsureUserHasCompany
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->currentCompany->has()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'You are not a member of an active company.'], 403)
                : redirect()->route('companies.none');
        }

        return $next($request);
    }
}
