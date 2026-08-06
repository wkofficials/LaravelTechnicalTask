<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/admin/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

});

Route::middleware(['auth', 'permission:import records'])->group(function () {
    Route::get('/imports/create', [ImportController::class, 'create'])->name('imports.create');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{importBatch}/status', [ImportController::class, 'status'])->name('imports.status');
});

Route::middleware(['auth'])->group(function () {
    Route::middleware('permission:view records')->group(function () {
        Route::get('/records', [RecordController::class, 'index'])->name('records.index');
    });

    Route::middleware('permission:create records')->group(function () {
        Route::get('/records/create', [RecordController::class, 'create'])->name('records.create');
        Route::post('/records', [RecordController::class, 'store'])->name('records.store');
    });

    Route::middleware('permission:edit records')->group(function () {
        Route::get('/records/{record}/edit', [RecordController::class, 'edit'])->name('records.edit');
        Route::put('/records/{record}', [RecordController::class, 'update'])->name('records.update');
    });

    Route::middleware('permission:delete records')->group(function () {
        Route::delete('/records/{record}', [RecordController::class, 'destroy'])->name('records.destroy');
    });
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
