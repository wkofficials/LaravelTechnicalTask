<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class PricingController extends Controller
{
    /**
     * Public pricing page, built from the plans a platform admin marked public.
     */
    public function __invoke(): View
    {
        return view('public.pricing', ['plans' => Plan::where('is_public', true)->orderBy('id')->get()]);
    }
}
