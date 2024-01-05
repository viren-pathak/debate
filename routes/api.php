<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DebateController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AdminController;

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
Route::post('/search', [DebateController::class, 'searchDebates']); // search Debate by tag , thesis and title
Route::get('/top-contributors', [DebateController::class, 'topContributors']); // Get list of top contributors in home page
Route::get('/overall-stats', [DebateController::class, 'overallStats']); // Get overall stats of diyun

Route::post('/debates/{debateId}/vote', [DebateController::class, 'vote']); // Add vote into debate
Route::get('/debates/{debateId}/vote-counts', [DebateController::class, 'getVoteCounts']); // get vote list

Route::get('/debates/{debateId}/commentsList', [DebateController::class, 'getComments']); // Get Comments List


Route::post('/debates/{parentId}/addProsChildDebate', [DebateController::class, 'addProsChildDebate']); // Add pros to debate
Route::post('/debates/{parentId}/addConsChildDebate', [DebateController::class, 'addConsChildDebate']); // Add Cons to debate

// Protetcted Routes (user Authentication needed for these APIs)
Route::middleware(['auth:sanctum', 'verified'])->group(function(){
    Route::post('/logout', [UserController::class, 'logout']); // API for user logout
    Route::post('/changepassword', [UserController::class, 'change_password']); // API for changing password when user logged in
    Route::get('/user-profile-details', [UserController::class, 'userProfileDetails']); // API TO get user profile
    Route::get('/user-contributions', [UserController::class, 'userContributions']); // Api for getting number of user contributions
    Route::get('/user-activity', [UserController::class, 'getUserActivity']);

    Route::post('/debates/{debateId}/addComments', [DebateController::class, 'addComment']); // Add Comments
    Route::put('/comments/{commentId}/editComment', [DebateController::class, 'editComment']); // Edit Comments
    Route::delete('/comments/{commentId}/hideComment', [DebateController::class, 'hideComment']); // hide Comments
}); 


// Admin APIs
Route::get('/admin/all-users', [AdminController::class, 'getAllUsers']);
Route::get('/admin/user/{userId}', [AdminController::class, 'getUserDetails']);
Route::delete('/admin/user/{userId}', [AdminController::class, 'deleteUser']);

Route::get('/admin/all-votes', [AdminController::class, 'getAllVotes']);
Route::delete('/admin/delete-vote/{id}', [AdminController::class, 'deleteVote']);

Route::get('/admin/all-comments', [AdminController::class, 'getAllComments']);
Route::delete('/admin/delete-comment/{id}', [AdminController::class, 'deleteComment']);

Route::get('/admin/all-debates', [AdminController::class, 'getAllDebates']);
Route::delete('/admin/delete-debate/{id}', [AdminController::class, 'deleteDebate']);

Route::get('/admin/all-stats', [AdminController::class, 'getAllStats']);