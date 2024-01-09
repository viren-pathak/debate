<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; // Import User model
use Illuminate\Support\Facades\Hash; // Import Facades to make password hashed
use Illuminate\Auth\Events\Registered;
use App\Notifications\VerifyEmailNotification; 
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use Illuminate\Support\Str;
use App\Http\Controllers\DebateController;
use App\Models\Debate;
use App\Models\Vote;
use App\Models\DebateComment;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /***  Function for USER registeration ***/

    public function register(Request $request){
        //request validtion
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed',
            'username' => 'required|unique:users'
        ]);

        // error message if email exists
        if (User::where('email', $request->email)->orWhere('username', $request->username)->first()) {
            return response([
                'message' => 'Email or Username Already Exists',
                'status' => 'failed'
            ], 200);
        }

        // Store data in table
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'verification_token' => \Str::random(40),
            'username' => $request->username
        ]);
        

        // Send verification email
        $user->notify(new VerifyEmailNotification($user->verification_token));



        // Create token for user
        $token = $user->createToken($request->email)->plainTextToken;

        // Response after successful registration
        return response([
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'email_verified_at' => $user->email_verified_at,
            'message' => 'Registration Successful, Please check your mailbox to verify email address',
            'status' => 'success'
        ], 201); 
    }



    /*** Function For USER Login ***/
    
    public function login(Request $request){
        //request validtion
        $request->validate([
            'email' => 'required_without:username|email',
            'password' => 'required',
            'username' => 'required_without:email'
        ]);

        // Get user email ID 
        $user = User::where('email', $request->email)->orWhere('username', $request->username)->first();

        //validate user password
        if ($user && $user->hasVerifiedEmail() && Hash::check($request->password, $user->password)) {

            // Create token for user in login
            $token = $user->createToken($request->email ?? $request->username)->plainTextToken;

            // Response after successful login
            return response([
                'token' => $token,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'email_verified_at' => $user->email_verified_at,
                'message' => 'Login Successful',
                'status' => 'success'
            ], 200);
        
        }

        // response on wrong credentials
        return response([
            'message' => 'The provided credentials are incorrect',
            'status' => 'failed'
        ], 401);
    }


    /*** Function to fetch user profile details for editing. ***/
    
    public function editProfile()
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // Return response with the user's current profile details
        return response()->json([
            'status' => 200,
            'user' => $user,
        ]);
    }


    /*** Function to update user profile details. ***/

    public function updateProfile(Request $request)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // Validate the request data
        $request->validate([
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'isProfilePrivate' => 'nullable|boolean',
            'biography' => 'nullable|string',
        ]);

        // Update profile picture if provided
        if ($request->hasFile('profile_picture')) {
            // Delete existing profile picture if any
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');
            $user->profile_picture = $profilePicturePath;
        }

        // Update other profile fields if provided
        $user->isProfilePrivate = $request->input('isProfilePrivate', $user->isProfilePrivate);
        $user->biography = $request->input('biography', $user->biography);

        // Save changes to the database
        $user->save();

        // Return response
        return response()->json([
            'status' => 200,
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }


    /*** Function For USER Logout ***/
    
    public function logout(Request $request){
        auth()->user()->tokens()->delete(); // delete temp login token after logout

        // Response after successful logout
        return response([
            'message' => 'Logout Successful',
            'status'=>'success'
        ], 200);
    }


    /*** Function to get SELF Profile Details DATA ***/

    public function getSelfProfileDetails(Request $request){
        $loggedUser = auth()->user(); // Retrieve the authenticated user

        // Check if the user is logged in
        if (!$loggedUser) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // Response for displaying user profile details
        return response()->json([
            'status' => 200,
            'username' => $loggedUser->username,
            'created_at' => $loggedUser->created_at->toDateTimeString(), // Format the creation time
            'total_received_thanks' => $loggedUser->total_received_thanks,
            'message' => 'User Profile Details',
        ], 200);
    }



    /*** FUNCTION TO GET NUMBERS OF SELF CONTRIBUTIONS DATA ***/

    public function getSelfContributions(Request $request)
    {
        $user = $request->user();

        // Fetch the sum of debates, pros, and child debates created by the user
        $totalClaims = Debate::where('user_id', $user->id)
            ->orWhere('side', 'pros')
            ->orWhereNotNull('parent_id')
            ->count();

        // Fetch total number of votes given by the user
        $totalVotes = Vote::whereHas('debate', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->count();

        // Fetch total number of comments made by the user
        $totalComments = DebateComment::where('user_id', $user->id)->count();

        // Calculate total contributions
        $totalContributions = $totalClaims + $totalVotes + $totalComments;

        // Update user contributions in the database
        $user->total_claims = $totalClaims;
        $user->total_votes = $totalVotes;
        $user->total_comments = $totalComments;
        $user->total_contributions = $totalContributions;
        $user->save();

        // Return the dashboard data
        return response()->json([
            'status' => 200,
            'userContributions' => [
                'totalClaims' => $totalClaims,
                'totalVotes' => $totalVotes,
                'totalComments' => $totalComments,
                'totalContributions' => $totalContributions,
            ],
        ], 200);
    }


    /*** FUNCTION TO GET SELF ACTIVITY DATA  ***/
    
    public function getSelfActivity(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // Get all debates created or participated by the user
        $debates = Debate::where('user_id', $user->id)
            ->orWhereHas('pros', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orWhereHas('cons', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        // Get comments made by the user
        $comments = DebateComment::where('user_id', $user->id)->get();

        // Get votes given by the user
        $votes = Vote::where('user_id', $user->id)->get();

        // Combine all activities
        $activities = collect([])
            ->merge($debates->map(function ($debate) {
                return [
                    'type' => 'debate',
                    'id' => $debate->parent_id ?? $debate->id,
                    'title' => $debate->parent_id ? Debate::find($debate->parent_id)->title : $debate->title,
                    'created_at' => $debate->created_at,
                ];
            }))
            ->merge($comments->map(function ($comment) {
                $debateId = $comment->debate->parent_id ?? $comment->debate_id;
                $parentDebate = Debate::where('id', $debateId)->first();

                return [
                    'type' => 'comment',
                    'id' => $parentDebate->parent_id ?? $debateId,
                    'title' => $parentDebate->title,
                    'created_at' => $comment->created_at,
                ];
            }))
            ->merge($votes->map(function ($vote) {
                $debateId = $vote->debate->parent_id ?? $vote->debate_id;
                $parentDebate = Debate::where('id', $debateId)->first();

                return [
                    'type' => 'vote',
                    'id' => $parentDebate->parent_id ?? $debateId,
                    'title' => $parentDebate->title,
                    'created_at' => $vote->created_at,
                ];
            }));

        // Group activities by debate ID
        $groupedActivities = $activities->groupBy('id');

        // Keep only the entry with the latest timestamp for each debate ID within the same month
        $filteredActivities = $groupedActivities->map(function ($group) {
            return $group->sortByDesc('created_at')->first();
        });

        // Sort the final activities by created_at in descending order
        $sortedActivities = $filteredActivities->sortByDesc('created_at');

        return response()->json([
            'status' => 200,
            'activity' => $sortedActivities->values()->all(),
        ], 200);
    }



    /*** FUNCTION TO GET USER DETAILS BY USER ID ***/

    public function getUserProfileDetails(Request $request, int $userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'User not found!',
            ], 404);
        }

        // Fetch user contributions
        $totalClaims = Debate::where('user_id', $user->id)
            ->orWhere('side', 'pros')
            ->orWhereNotNull('parent_id')
            ->count();

        $totalVotes = Vote::whereHas('debate', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->count();

        $totalComments = DebateComment::where('user_id', $user->id)->count();

        $totalContributions = $totalClaims + $totalVotes + $totalComments;

        // Fetch user activities
        $debates = Debate::where('user_id', $user->id)
            ->orWhereHas('pros', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orWhereHas('cons', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        $comments = DebateComment::where('user_id', $user->id)->get();

        $votes = Vote::where('user_id', $user->id)->get();

        $activities = collect([])
            ->merge($debates->map(function ($debate) {
                return [
                    'type' => 'debate',
                    'id' => $debate->parent_id ?? $debate->id,
                    'title' => $debate->parent_id ? Debate::find($debate->parent_id)->title : $debate->title,
                    'created_at' => $debate->created_at,
                ];
            }))
            ->merge($comments->map(function ($comment) {
                $debateId = $comment->debate->parent_id ?? $comment->debate_id;
                $parentDebate = Debate::where('id', $debateId)->first();

                return [
                    'type' => 'comment',
                    'id' => $parentDebate->parent_id ?? $debateId,
                    'title' => $parentDebate->title,
                    'created_at' => $comment->created_at,
                ];
            }))
            ->merge($votes->map(function ($vote) {
                $debateId = $vote->debate->parent_id ?? $vote->debate_id;
                $parentDebate = Debate::where('id', $debateId)->first();

                return [
                    'type' => 'vote',
                    'id' => $parentDebate->parent_id ?? $debateId,
                    'title' => $parentDebate->title,
                    'created_at' => $vote->created_at,
                ];
            }));

        $groupedActivities = $activities->groupBy('id');
        $filteredActivities = $groupedActivities->map(function ($group) {
            return $group->sortByDesc('created_at')->first();
        });
        $sortedActivities = $filteredActivities->sortByDesc('created_at')->values()->all();

        return response()->json([
            'status' => 200,
            'user_id' => $user->id,
            'username' => $user->username,
            'created_at' => $user->created_at->toDateTimeString(),
            'total_received_thanks' => $user->total_received_thanks,
            'userContributions' => [
                'totalClaims' => $totalClaims,
                'totalVotes' => $totalVotes,
                'totalComments' => $totalComments,
                'totalContributions' => $totalContributions,
            ],
            'activity' => $sortedActivities,
        ], 200);
    }
    

    /*** Function to change the password when USER is logged in  ***/

    public function change_password(Request $request){

        // validate used password
        $request->validate([
            'password' => 'required|confirmed',
        ]);

        $loggeduser = auth()->user(); // validate user logged in or not 
        
        $loggeduser->password = Hash::make($request->password); // make password hashed
        $loggeduser->save();

        // Response after password changed
        return response([
            'message' => 'Password Changed Successfully',
            'status'=>'success'
        ], 200);
    }

}

