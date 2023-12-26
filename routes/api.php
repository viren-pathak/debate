<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DebateController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ContactController;

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

// Public routes without authentication
Route::post('/register', [UserController::class, 'register']); // API for user registration
Route::post('/login', [UserController::class, 'login']); // API for user login
Route::post('/send-reset-password-email', [PasswordResetController::class, 'send_reset_password_email']); // API TO send Reset Password Email
Route::post('/reset-password/{token}', [PasswordResetController::class, 'reset_password']); // API For reseting password
Route::get('/verify-email/{token}', [VerificationController::class, 'verify'])->name('verification.verify');

Route::post('/contact-form', [ContactController::class, 'sendMail'])->name('addContact'); // API FOR SUpport Contact form

Route::get('showalldebate', [DebateController::class, 'getalldebates']);//show all the debates  
Route::post('createdebate', [DebateController::class, 'storetodb']);//create debates
Route::post('getdebatebyid/{id}', [DebateController::class, 'getbyid']);//get debate by id
Route::get('getdebatebyid/{id}/editdebate', [DebateController::class, 'editdebateindb']);//edit the debate
Route::put('getdebatebyid/{id}/editdebate', [DebateController::class, 'updatedebate']);//update debate
Route::delete('getdebatebyid/{id}/deletedebate', [DebateController::class, 'destroydebate']);//delete debate
Route::put('getdebatebyid/{id}/imageupload', [DebateController::class, 'debateimageupload']);// upload images
Route::get('/debates/tags', [DebateController::class, 'getAllTags']);//display all tags
Route::get('/debates/tag/{tag}', [DebateController::class, 'getDebatesByTag']);//get debates by tag
Route::get('getdebatebyid/{id}/displaydebate', [DebateController::class, 'getDebateByIdWithHierarchy']); // Display Debate by ID

Route::post('/debates/{debateId}/vote', [DebateController::class, 'vote']); // Add vote into debate
Route::get('/debates/{debateId}/vote-counts', [DebateController::class, 'getVoteCounts']); // get vote list

Route::get('/debates/{debateId}/commentsList', [DebateController::class, 'getComments']); // Get Comments List


Route::post('/debates/{parentId}/addProsChildDebate', [DebateController::class, 'addProsChildDebate']);
Route::post('/debates/{parentId}/addConsChildDebate', [DebateController::class, 'addConsChildDebate']);
Route::get('/debates/{parentId}/getProsChildDebates', [DebateController::class, 'getProsChildDebates']);
Route::get('/debates/{parentId}/getConsChildDebates', [DebateController::class, 'getConsChildDebates']);

// Protetcted Routes (user Authentication needed for these APIs)
Route::middleware(['auth:sanctum', 'verified'])->group(function(){
    Route::post('/logout', [UserController::class, 'logout']); // API for user logout
    Route::get('/loggeduser', [UserController::class, 'logged_user']); // API for logged In user details
    Route::post('/changepassword', [UserController::class, 'change_password']); // API for changing password when user logged in

    Route::post('/debates/{debateId}/addComments', [DebateController::class, 'addComment']); // Add Comments
    Route::put('/comments/{commentId}/editComment', [DebateController::class, 'editComment']); // Edit Comments
    Route::delete('/comments/{commentId}/hideComment', [DebateController::class, 'hideComment']); // hide Comments
}); 