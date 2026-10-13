<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\DatasetRecordController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportTemplateController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Platform\BillingController as PlatformBillingController;
use App\Http\Controllers\Platform\CompanyController as PlatformCompanyController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\PricingController;
use Illuminate\Support\Facades\Route;

// Public site.
Route::view('/', 'welcome')->name('home');
Route::get('/pricing', PricingController::class)->name('pricing');
Route::view('/terms', 'public.legal', ['page' => 'terms', 'title' => 'Terms of Service'])->name('terms');
Route::view('/privacy', 'public.legal', ['page' => 'privacy', 'title' => 'Privacy Policy'])->name('privacy');

// Invitation links work for guests and for signed-in users without a company yet.
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/register', [InvitationController::class, 'register'])
    ->middleware(['guest', 'throttle:10,1'])->name('invitations.register');
// Verified only: otherwise someone could register the invitee's address
// first and take over the membership (account pre-hijacking).
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->middleware(['auth', 'verified', 'throttle:10,1'])->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::get('/no-company', [CompanyController::class, 'none'])->name('companies.none');
    Route::post('/companies/{company}/switch', [CompanyController::class, 'switch'])->name('companies.switch');

    // A user's own notifications, across all their companies.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Company workspace: requires a verified email and an active company (set by SetCurrentCompany).
Route::middleware(['auth', 'verified', 'company'])->group(function () {

    Route::get('/admin/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware('permission:import records')->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::get('/imports/create', [ImportController::class, 'create'])->name('imports.create');
        Route::post('/imports', [ImportController::class, 'store'])->middleware('throttle:20,1')->name('imports.store');
        Route::get('/imports/{importBatch}', [ImportController::class, 'show'])->name('imports.show');
        Route::get('/imports/{importBatch}/status', [ImportController::class, 'status'])->name('imports.status');
        Route::post('/imports/{importBatch}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
        Route::post('/imports/{importBatch}/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');
        Route::get('/imports/{importBatch}/errors.csv', [ImportController::class, 'downloadErrors'])->name('imports.errors');

        Route::get('/templates', [ImportTemplateController::class, 'index'])->name('templates.index');
        Route::get('/data/{dataset}/sample.csv', [ImportController::class, 'sample'])->name('imports.sample');
    });

    Route::middleware('permission:manage templates')->group(function () {
        Route::patch('/templates/{template}', [ImportTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [ImportTemplateController::class, 'destroy'])->name('templates.destroy');
    });

    // Records of any registered dataset ({dataset} is resolved via DatasetRegistry).
    Route::prefix('/data/{dataset}/records')->name('records.')->group(function () {
        Route::get('/', [DatasetRecordController::class, 'index'])->middleware('permission:view records')->name('index');
        Route::get('/create', [DatasetRecordController::class, 'create'])->middleware('permission:create records')->name('create');
        Route::post('/', [DatasetRecordController::class, 'store'])->middleware('permission:create records')->name('store');
        Route::get('/{record}/edit', [DatasetRecordController::class, 'edit'])->middleware('permission:edit records')->name('edit');
        Route::put('/{record}', [DatasetRecordController::class, 'update'])->middleware('permission:edit records')->name('update');
        Route::delete('/{record}', [DatasetRecordController::class, 'destroy'])->middleware('permission:delete records')->name('destroy');
        Route::post('/export', [DataExportController::class, 'store'])->middleware('permission:export records')->name('export');
    });

    Route::middleware('permission:export records')->group(function () {
        Route::get('/exports/{export}/status', [DataExportController::class, 'status'])->name('exports.status');
        Route::get('/exports/{export}/download', [DataExportController::class, 'download'])->name('exports.download');
    });

    // Pre-dataset URL, kept for old bookmarks.
    Route::redirect('/records', '/data/contacts/records');

    Route::get('/usage', UsageController::class)->middleware('permission:view usage')->name('usage');
    Route::get('/audit-log', AuditLogController::class)->middleware('permission:view audit log')->name('audit.index');

    Route::middleware('permission:manage billing')->prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('/invoices/{invoice}', [BillingController::class, 'showInvoice'])->name('invoices.show');
        Route::post('/request', [BillingController::class, 'requestUpgrade'])->middleware('throttle:5,1')->name('request');
        Route::post('/cancel', [BillingController::class, 'cancel'])->name('cancel');
        Route::post('/resume', [BillingController::class, 'resume'])->name('resume');
    });

    Route::middleware('permission:manage company')->group(function () {
        Route::get('/company/settings', [CompanySettingsController::class, 'edit'])->name('company.settings');
        Route::put('/company/settings', [CompanySettingsController::class, 'update'])->name('company.settings.update');
    });

    Route::middleware('permission:manage team')->prefix('team')->name('team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('/invitations', [TeamController::class, 'invite'])->name('invite');
        Route::delete('/invitations/{invitation}', [TeamController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::patch('/members/{user}', [TeamController::class, 'updateRole'])->name('members.update');
        Route::delete('/members/{user}', [TeamController::class, 'remove'])->name('members.remove');
    });
});

// Platform administration (super admins only; 404 for everyone else).
Route::middleware(['auth', 'verified', 'platform'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
    Route::get('/companies', [PlatformCompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [PlatformCompanyController::class, 'show'])->name('companies.show');
    Route::put('/companies/{company}', [PlatformCompanyController::class, 'update'])->name('companies.update');
    Route::get('/plans', [PlatformController::class, 'plans'])->name('plans');
    Route::put('/plans/{plan}', [PlatformController::class, 'updatePlan'])->name('plans.update');

    Route::get('/billing', [PlatformBillingController::class, 'index'])->name('billing');
    Route::get('/invoices/{invoice}', [PlatformBillingController::class, 'showInvoice'])->name('invoices.show');
    Route::post('/companies/{company}/invoices', [PlatformBillingController::class, 'issueInvoice'])->name('companies.invoices.store');
    Route::post('/companies/{company}/trial', [PlatformBillingController::class, 'startTrial'])->name('companies.trial');
    Route::post('/invoices/{invoice}/paid', [PlatformBillingController::class, 'markPaid'])->name('invoices.paid');
    Route::post('/invoices/{invoice}/void', [PlatformBillingController::class, 'void'])->name('invoices.void');
});

require __DIR__.'/auth.php';
