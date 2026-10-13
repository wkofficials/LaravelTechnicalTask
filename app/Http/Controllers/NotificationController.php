<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's in-app notifications (across all their companies).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(25),
        ]);
    }

    /**
     * Mark one as read and go to what it is about — switching to the
     * notification's company first if the user is a member of it.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $company = Company::find($notification->data['company_id'] ?? null);

        if ($company && $company->isActive() && $request->user()->belongsToCompany($company)) {
            $request->user()->switchCompany($company);
        }

        // Only ever redirect within this app (stored URLs are relative paths).
        $url = $notification->data['url'] ?? '';

        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
