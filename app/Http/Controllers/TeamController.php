<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use App\Support\Audit;
use App\Support\PlanLimits;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Team management for the current company. Every route here requires the
 * "manage team" permission (see routes/web.php).
 */
class TeamController extends Controller
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function index(): View
    {
        $company = $this->company();

        $members = $company->users()->orderBy('name')->get()
            ->map(fn (User $user) => ['user' => $user, 'role' => $company->roleOf($user)]);

        $invitations = CompanyInvitation::pending()->with('inviter')->latest()->get();

        return view('team.index', [
            'company' => $company,
            'members' => $members,
            'invitations' => $invitations,
            'assignableRoles' => Company::ASSIGNABLE_ROLES,
            'seatsUsed' => PlanLimits::for($company)->seatsUsed(),
            'seatLimit' => PlanLimits::for($company)->plan()->max_users,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $company = $this->company();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(Company::ASSIGNABLE_ROLES)],
        ]);

        $email = Str::lower($validated['email']);

        $alreadyMember = $company->users()->where('email', $email)->exists();

        if ($alreadyMember) {
            return back()->withErrors(['email' => 'This person is already a member of the company.'])->withInput();
        }

        // A re-invite replaces a pending invitation, so it takes no new seat.
        $isReinvite = CompanyInvitation::where('email', $email)->pending()->exists();

        if (! $isReinvite && $seatProblem = PlanLimits::for($company)->checkCanAddMember()) {
            return back()->withErrors(['email' => $seatProblem])->withInput();
        }

        CompanyInvitation::where('email', $email)->whereNull('accepted_at')->delete();

        [$invitation, $token] = CompanyInvitation::issue($email, $validated['role'], $request->user());

        Notification::route('mail', $email)->notify(new CompanyInvitationNotification($invitation, $token));

        Audit::log('team.invited', $invitation, ['email' => Audit::maskEmail($email), 'role' => $validated['role']]);

        return back()->with('status', "Invitation sent to {$email}.");
    }

    public function revokeInvitation(CompanyInvitation $invitation): RedirectResponse
    {
        // Route binding is tenant-scoped, so only this company's invitations resolve.
        $invitation->delete();

        Audit::log('team.invitation_revoked', $invitation, ['email' => Audit::maskEmail($invitation->email)]);

        return back()->with('status', 'Invitation revoked.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $company = $this->company();
        $this->ensureManageableMember($request, $company, $user);

        $validated = $request->validate([
            'role' => ['required', Rule::in(Company::ASSIGNABLE_ROLES)],
        ]);

        $previousRole = $company->roleOf($user);
        $company->setRoleOf($user, $validated['role']);

        Audit::log('team.role_changed', $user, ['from' => $previousRole, 'to' => $validated['role']]);

        return back()->with('status', "{$user->name} is now {$validated['role']}.");
    }

    public function remove(Request $request, User $user): RedirectResponse
    {
        $company = $this->company();
        $this->ensureManageableMember($request, $company, $user);

        $role = $company->roleOf($user);
        $company->removeMember($user);

        Audit::log('team.member_removed', $user, ['role' => $role]);

        return back()->with('status', "{$user->name} was removed from {$company->name}.");
    }

    /**
     * A member can be changed/removed only if they belong to this company,
     * aren't the acting user, and aren't an owner (owners can't be demoted or
     * removed through the team screen, which keeps every company owned).
     */
    private function ensureManageableMember(Request $request, Company $company, User $user): void
    {
        abort_unless($user->belongsToCompany($company), 404);
        abort_if($user->is($request->user()), 403, 'You cannot change your own membership.');
        abort_if($company->roleOf($user) === 'owner', 403, 'The company owner cannot be changed here.');
    }

    private function company(): Company
    {
        return $this->currentCompany->get();
    }
}
