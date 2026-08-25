<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\ResolveTenant;
use App\Livewire\Pages;
use App\Livewire\Portal;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| Public
|---------------------------------------------------------------------------
*/

Route::view('/', 'marketing.home')->name('home');

// The decks. Static pages, versioned with the app rather than emailed around.
Route::view('/deck', 'deck.pitch')->name('deck');
Route::view('/deck/technical', 'deck.technical')->name('deck.technical');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');

    // Forgotten passwords. Throttled because the endpoint sends mail on demand.
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|---------------------------------------------------------------------------
| Console — agency staff
|---------------------------------------------------------------------------
|
| ResolveTenant binds the signed-in user's agency into the container before
| anything reads it, which is what makes the global tenant scope on every
| model safe. EnsureStaff keeps buyers out: a client who guesses /app must
| land in their own portal, not in the agency's pipeline.
*/

Route::middleware(['auth', ResolveTenant::class, EnsureStaff::class])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::get('/', Pages\Dashboard::class)->name('dashboard');
        Route::get('/insights', Pages\Insights::class)->name('insights');
        Route::get('/ask', Pages\Ask::class)->name('ask');

        Route::get('/sales', Pages\Sales::class)->name('sales');
        Route::get('/leads', Pages\Leads::class)->name('leads');
        Route::get('/properties', Pages\Properties::class)->name('properties');
        Route::get('/marketing', Pages\Marketing::class)->name('marketing');
        Route::get('/customers', Pages\Customers::class)->name('customers');
        Route::get('/finance', Pages\Finance::class)->name('finance');
        Route::get('/feedback', Pages\Feedback::class)->name('feedback');

        Route::get('/analytics', Pages\Analytics::class)->name('analytics');
        Route::get('/reports', Pages\Reports::class)->name('reports');
        Route::get('/activity', Pages\Activity::class)->name('activity');
        Route::get('/settings', Pages\Settings::class)->name('settings');

        Route::post('/role', [AuthController::class, 'switchRole'])->name('role');
    });

/*
|---------------------------------------------------------------------------
| Portal — buyers
|---------------------------------------------------------------------------
|
| Same database, same tenant scope, one extra constraint: a client only ever
| sees rows tied to their own customer record.
*/

Route::middleware(['auth', ResolveTenant::class, EnsureClient::class])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', Portal\Overview::class)->name('overview');
        Route::get('/payments', Portal\Payments::class)->name('payments');
        Route::get('/documents', Portal\Documents::class)->name('documents');
        Route::get('/support', Portal\Support::class)->name('support');
    });
