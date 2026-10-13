<?php

namespace App\Http\Controllers;

use App\Models\CompanyInvitation;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Accepting a company invitation from the emailed link.
 *
 * The invitation only works for the email address it was sent to: a
 * signed-in user with a different email can't use it, and a new account
 * created from it always gets the invited email.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = $this->findOrFail($token);
        $user = $request->user();

        if (! $user && User::where('email', $invitation->email)->exists()) {
            // Existing account: sign in first, then come back here.
            return redirect()->guest(route('login'))
                ->with('status', 'Please sign in to accept your invitation.');
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'emailMatches' => $user && $user->email === $invitation->email,
        ]);
    }

    /**
     * A signed-in user accepts an invitation addressed to their email.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findOrFail($token);
        $user = $request->user();

        abort_unless($user->email === $invitation->email, 403, 'This invitation was sent to a different email address.');

        $this->join($invitation, $user);

        return redirect()->route('dashboard')->with('status', "Welcome to {$invitation->company->name}!");
    }

    /**
     * A new person creates their account from the invitation.
     */
    public function register(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findOrFail($token);

        abort_if(User::where('email', $invitation->email)->exists(), 409, 'An account with this email already exists. Please sign in.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
            ]);

            // Opening the emailed link proves ownership of the address.
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->join($invitation, $user);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', "Welcome to {$invitation->company->name}!");
    }

    private function join(CompanyInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            // Lock and re-check so a link can't be redeemed twice concurrently.
            $fresh = CompanyInvitation::withoutGlobalScope('company')
                ->lockForUpdate()
                ->pending()
                ->findOrFail($invitation->id);

            $fresh->company->addMember($user, $fresh->role);

            Audit::log('team.invitation_accepted', $fresh, ['role' => $fresh->role], $fresh->company, $user);
            $fresh->forceFill(['accepted_at' => now()])->save();

            $user->switchCompany($fresh->company);
        });
    }

    private function findOrFail(string $token): CompanyInvitation
    {
        $invitation = CompanyInvitation::findPendingByToken($token);

        abort_unless($invitation && $invitation->company->isActive(), 404, 'This invitation is invalid or has expired.');

        return $invitation;
    }
}
