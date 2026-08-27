<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureRealtor;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\ResolveTenant;
use App\Livewire\Pages;
use App\Livewire\Portal;
use App\Livewire\Public;
use App\Livewire\Realtor;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| Public
|---------------------------------------------------------------------------
|
| The catalogue is open. Someone should be able to see what is for sale before
| deciding whether to hand over their details — a property platform that
| demands registration to show a price is one people leave.
*/

Route::get('/', Public\Home::class)->name('home');
Route::get('/properties', Public\Catalogue::class)->name('properties');
Route::get('/properties/{ref}', Public\PropertyDetail::class)->name('property');

// The questionnaire. Carries ?ref= from a realtor's link or an advert, and
// writes a lead — deliberately not an account.
Route::get('/enquire', Public\Enquire::class)->name('enquire');

// The decks. Static pages, versioned with the app rather than emailed around.
Route::view('/deck', 'deck.pitch')->name('deck');
Route::view('/deck/technical', 'deck.technical')->name('deck.technical');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    // Investor registration. ?ref=CODE arrives from a realtor's link.
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');

    // Realtors apply for themselves and wait for approval.
    Route::get('/realtor/apply', [AuthController::class, 'showRealtorApply'])->name('realtor.apply');
    Route::post('/realtor/apply', [AuthController::class, 'realtorApply'])->name('realtor.apply.attempt');

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
| Console — admins
|---------------------------------------------------------------------------
|
| ResolveTenant binds the house account before anything reads it, which is what
| makes the global scope on every model safe. EnsureStaff keeps everyone else
| out: a realtor or an investor who guesses /app is walked to their own area
| rather than shown an error.
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

        // The two queues that are most of an admin's actual day.
        Route::get('/realtors', Pages\Realtors::class)->name('realtors');
        Route::get('/commitments', Pages\Commitments::class)->name('commitments');
        Route::get('/materials', Pages\Materials::class)->name('materials');

        Route::get('/analytics', Pages\Analytics::class)->name('analytics');
        Route::get('/reports', Pages\Reports::class)->name('reports');
        Route::get('/activity', Pages\Activity::class)->name('activity');
        Route::get('/settings', Pages\Settings::class)->name('settings');

        Route::post('/role', [AuthController::class, 'switchRole'])->name('role');
    });

/*
|---------------------------------------------------------------------------
| Realtors
|---------------------------------------------------------------------------
|
| EnsureRealtor carries the approval gate. A pending realtor can sign in and
| reach exactly one screen; everything else redirects there until an admin
| approves them.
*/

Route::middleware(['auth', ResolveTenant::class, EnsureRealtor::class])
    ->prefix('realtor')
    ->name('realtor.')
    ->group(function () {
        Route::get('/pending', Realtor\Pending::class)->name('pending');
        Route::get('/', Realtor\Dashboard::class)->name('dashboard');
        Route::get('/leads', Realtor\Leads::class)->name('leads');
        Route::get('/properties', Realtor\Properties::class)->name('properties');
        Route::get('/materials', Realtor\Materials::class)->name('materials');
        Route::get('/ask', Realtor\Ask::class)->name('ask');
        Route::get('/commission', Realtor\Commission::class)->name('commission');
    });

/*
|---------------------------------------------------------------------------
| Investors
|---------------------------------------------------------------------------
|
| Same database, same tenant, one extra constraint: an investor only ever sees
| rows tied to their own record.
*/

Route::middleware(['auth', ResolveTenant::class, EnsureClient::class])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', Portal\Overview::class)->name('overview');
        Route::get('/browse', Portal\Browse::class)->name('browse');
        Route::get('/commit/{ref}', Portal\Commit::class)->name('commit');
        Route::get('/payments', Portal\Payments::class)->name('payments');
        Route::get('/documents', Portal\Documents::class)->name('documents');
        Route::get('/support', Portal\Support::class)->name('support');
        Route::get('/ask', Portal\Ask::class)->name('ask');
    });
