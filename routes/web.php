<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Three surfaces, three role gates:
|   /app      household
|   /agent    collection agent
|   /console  facility staff and admin
*/

Route::view('/', 'marketing.home')->name('home');

// --- guest ------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', App\Livewire\Auth\Login::class)->name('login');
    Route::get('/register', App\Livewire\Auth\Register::class)->name('register');
    Route::get('/forgot-password', App\Livewire\Auth\ForgotPassword::class)->name('password.request');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');

// --- household --------------------------------------------------------------
Route::middleware(['auth', EnsureRole::class . ':household'])->prefix('app')->group(function () {
    Route::get('/', App\Livewire\Household\Home::class)->name('app');
    Route::get('/request', App\Livewire\Household\RequestPickup::class)->name('app.request');
    Route::get('/track', App\Livewire\Household\Track::class)->name('app.track');
    Route::get('/wallet', App\Livewire\Household\Wallet::class)->name('app.wallet');
    Route::get('/profile', App\Livewire\Household\Profile::class)->name('app.profile');
});

// --- agent ------------------------------------------------------------------
Route::middleware(['auth', EnsureRole::class . ':agent'])->prefix('agent')->group(function () {
    Route::get('/', App\Livewire\Agent\Today::class)->name('agent');
    Route::get('/jobs', App\Livewire\Agent\Jobs::class)->name('agent.jobs');
    Route::get('/pickup/{batch}', App\Livewire\Agent\Pickup::class)->name('agent.pickup');
    Route::get('/route', App\Livewire\Agent\RoutePlan::class)->name('agent.route');
    Route::get('/earnings', App\Livewire\Agent\Earnings::class)->name('agent.earnings');
    Route::get('/profile', App\Livewire\Agent\Profile::class)->name('agent.profile');
});

// --- facility console -------------------------------------------------------
Route::middleware(['auth', EnsureRole::class . ':staff,admin'])->prefix('console')->group(function () {
    Route::get('/', App\Livewire\Console\Overview::class)->name('console');
    Route::get('/pipeline', App\Livewire\Console\Pipeline::class)->name('console.pipeline');
    Route::get('/batches', App\Livewire\Console\Batches::class)->name('console.batches');
    Route::get('/inspection', App\Livewire\Console\Inspection::class)->name('console.inspection');
    Route::get('/processing', App\Livewire\Console\Processing::class)->name('console.processing');
    Route::get('/households', App\Livewire\Console\Households::class)->name('console.households');
    Route::get('/agents', App\Livewire\Console\Agents::class)->name('console.agents');
    Route::get('/payments', App\Livewire\Console\Payments::class)->name('console.payments');
    Route::get('/analytics', App\Livewire\Console\Analytics::class)->name('console.analytics');
    Route::get('/integrity', App\Livewire\Console\Integrity::class)->name('console.integrity');
});

/*
| Public batch trace. This is what the QR sticker on a bag resolves to, so a
| household, an agent or an inspector can scan and see the same record.
*/
Route::get('/b/{code}', App\Livewire\BatchTrace::class)->name('batch.trace');
