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

/*
******** Public routes without authentication ********
*/

// USER RELATED APIs
Route::post('/register', [UserController::class, 'register']); // API for user registration
Route::post('/login', [UserController::class, 'login']); // API for user login
Route::post('/send-reset-password-email', [PasswordResetController::class, 'send_reset_password_email']); // API TO send Reset Password Email
Route::post('/reset-password/{token}', [PasswordResetController::class, 'reset_password']); // API For reseting password
Route::get('/verify-email/{token}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::get('/user/{userId}/profile-details', [UserController::class, 'getUserProfileDetails']); // Get User details by user ID


// DEBATE RELATED APIs
Route::get('showalldebate', [DebateController::class, 'getalldebates']);//show all the debates  
Route::post('createdebate', [DebateController::class, 'storetodb']);//create debates
Route::get('getdebatebyid/{id}/editdebate', [DebateController::class, 'editdebateindb']);//edit the debate
Route::put('getdebatebyid/{id}/updatedebate', [DebateController::class, 'updatedebate']);//update debate
Route::delete('getdebatebyid/{id}/deletedebate', [DebateController::class, 'destroydebate']);//delete debate
Route::put('getdebatebyid/{id}/imageupload', [DebateController::class, 'debateimageupload']);// upload images
Route::put('/debates/{debateId}/archive', [DebateController::class, 'archiveDebate']); //archive debate hierarchy
Route::get('/debates/{debateId}/all-debates-with-archived', [DebateController::class, 'getAllDebatesWithArchived']);
Route::get('/debates/tags', [DebateController::class, 'getAllTags']);//display all tags
Route::get('/debates/tag/{tag}', [DebateController::class, 'getDebatesByTag']);//get debates by tag
Route::get('getdebatebyid/{id}/displaydebate', [DebateController::class, 'getDebateByIdWithHierarchy']); // Display Debate by ID
Route::post('/search', [DebateController::class, 'searchDebates']); // search Debate by tag , thesis and title


Route::get('/debates/activity/{debateId}', [DebateController::class, 'activityFilter']); // Activity notification  filter 
Route::get('/debates/{debateId}/votes-perspective', [DebateController::class, 'votesPerspective']); // Votes perspective


Route::post('/debates/{debateId}/vote', [DebateController::class, 'vote']); // Add vote into debate
Route::get('/debates/{debateId}/vote-counts', [DebateController::class, 'getVoteCounts']); // get vote list

Route::get('/debates/{debateId}/commentsList', [DebateController::class, 'getComments']); // Get Comments List

Route::post('/debates/{parentId}/addProsChildDebate', [DebateController::class, 'addProsChildDebate']); // Add pros to debate
Route::post('/debates/{parentId}/addConsChildDebate', [DebateController::class, 'addConsChildDebate']); // Add Cons to debate


// Admin APIs
Route::get('/admin/all-users', [AdminController::class, 'getAllUsers']); // get list of all users
Route::get('/admin/user/{userId}', [AdminController::class, 'getUserDetails']); // get user details by user ID
Route::delete('/admin/user/{userId}', [AdminController::class, 'deleteUser']); // delete user by user ID
Route::get('/admin/all-votes', [AdminController::class, 'getAllVotes']); // get list of all votes
Route::delete('/admin/delete-vote/{id}', [AdminController::class, 'deleteVote']); // delete vote by vote ID
Route::get('/admin/all-comments', [AdminController::class, 'getAllComments']); // get list of all comments
Route::delete('/admin/delete-comment/{id}', [AdminController::class, 'deleteComment']); // delete comment by comment ID
Route::get('/admin/all-debates', [AdminController::class, 'getAllDebates']); // get list of all debates
Route::delete('/admin/delete-debate/{id}', [AdminController::class, 'deleteDebate']); // delete debate by debate ID
Route::get('/admin/all-stats', [AdminController::class, 'getAllStats']); // get all stats 
Route::post('/admin/add-tag', [AdminController::class, 'addTag']); // add tag by admins


// ADDITIONAL APIs FOR HOME PAGE AND STATIC PAGES
Route::get('/top-contributors', [DebateController::class, 'topContributors']); // Get list of top contributors in home page
Route::get('/overall-stats', [DebateController::class, 'overallStats']); // Get overall stats of diyun
Route::post('/contact-form', [ContactController::class, 'sendMail'])->name('addContact'); // API FOR SUpport Contact form




/*
******* Protetcted Routes (user Authentication needed for these APIs) *******
*/

Route::middleware(['auth:sanctum', 'verified'])->group(function(){

    // USER RELATED PROTECTED APIs
    Route::post('/logout', [UserController::class, 'logout']); // API for user logout
    Route::post('/changepassword', [UserController::class, 'change_password']); // API for changing password when user logged in
    Route::get('/edit-profile', [UserController::class, 'editProfile']);    // Fetch user profile details for editing
    Route::post('/update-profile', [UserController::class, 'updateProfile']);   // Update user profile details
    Route::get('/my-profile-details', [UserController::class, 'getSelfProfileDetails']); // API TO get user profile
    Route::get('/my-contributions', [UserController::class, 'getSelfContributions']); // Api for getting number of user contributions
    Route::get('/my-activity', [UserController::class, 'getSelfActivity']);


    // DEBATE RELATED PROTECTED APIs
    Route::put('/debates/move-child/{childDebateId}', [DebateController::class, 'moveChildDebate']); // Move child debate
    Route::post('/debates/{debateId}/addComments', [DebateController::class, 'addComment']); // Add Comments
    Route::put('/comments/{commentId}/editComment', [DebateController::class, 'editComment']); // Edit Comments
    Route::delete('/comments/{commentId}/hideComment', [DebateController::class, 'hideComment']); // hide Comments
    Route::post('/debates/{debateId}/thanks', [DebateController::class, 'giveThanks']); // Thank author of the debate
    Route::post('/debates/{debateId}/add-bookmark', [DebateController::class, 'toggleBookmark']); // Add debate to bookmark
    Route::get('/debates/{debateId}/my-bookmarks', [DebateController::class, 'getBookmarkedDebates']); // Get list of bookmarks by debate ID
    Route::put('/debates/{debateId}/users/{userId}/change-role', [DebateController::class, 'changeUserRole']); // Change user role in debate by owner

    // CONTRIBUTIONS RELATED APIs
    Route::get('/debates/{debateId}/my-claims', [DebateController::class, 'getClaimsByDebate']); // Get list of claims by Debate ID
    Route::get('/debates/{debateId}/my-contributions', [DebateController::class, 'getContributionsRecursive']); // Get list of contributions by Debate ID
    Route::get('/debates/{debateId}/my-comments', [DebateController::class, 'getCommentsByDebate']); // Get list of contributions by Debate ID

}); 


