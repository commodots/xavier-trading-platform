<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
Route::get('/{any}', function () {
    return view('app'); // or the name of your main Vue entry blade
})->where('any', '^(?!api).*$');
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Crypto pages
    Route::get('/crypto/deposit', function () {
        return Inertia::render('Crypto/Deposit');
    })->name('crypto.deposit');

    Route::get('/crypto/withdraw', function () {
        return Inertia::render('Crypto/Withdraw');
    })->name('crypto.withdraw');
});

require __DIR__.'/auth.php';
