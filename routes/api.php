<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\VerificationController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes with authentication
Route::post('/register', [UserController::class, 'register']); // API for user registration
Route::post('/login', [UserController::class, 'login']); // API for user login
Route::post('/send-reset-password-email', [PasswordResetController::class, 'send_reset_password_email']); // API TO send Reset Password Email
Route::post('/reset-password/{token}', [PasswordResetController::class, 'reset_password']); // API For reseting password
Route::get('/verify-email/{token}', [VerificationController::class, 'verify'])->name('verification.verify');


// Protetcted Routes
Route::middleware(['auth:sanctum', 'verified'])->group(function(){
    Route::post('/logout', [UserController::class, 'logout']); // API for user logout
    Route::get('/loggeduser', [UserController::class, 'logged_user']); // API for logged In user details
    Route::post('/changepassword', [UserController::class, 'change_password']); // API for changing password when user logged in
}); 