<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Password reset link route (accessed from email)
Route::get('/password/reset/{token}', [AuthController::class, 'showResetForm'])
    ->name('password.reset');

// Add login route for Laravel's default auth redirects
Route::get('/login', function () {
    return redirect(config('app.frontend_url').'/auth');
})->name('login');
