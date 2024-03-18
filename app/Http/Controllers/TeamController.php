<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Notifications\TeamInvitationNotification;
use App\Models\Debate;
use App\Models\User;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamInviteLink;

class TeamController extends Controller
{
    
    /*** FUNCTION TO CREATE A NEW TEAM ***/
    public function createTeam(Request $request)
    {
        // Retrieve the authenticated user
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        //validate the request into input
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Generate a URL-friendly team handle
        $teamHandle = Str::slug($request->name);

        // create team and store data in teams table
        $team = Team::create([
            'name' => $request->name,
            'team_handle' => $teamHandle,
            'team_creator_id' => $user->id, // Assign the current user id as the team creator
        ]);

        // Assign the creator as owner in team_members table
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => 'owner',
        ]);

        // response after succesful team creation
        return response()->json([
            'status' => 201,
            'message' => 'Team created successfully!',
            'team' => $team,
        ], 201);
    }



    /*** FUNCTION TO UPDATE TEAM DETAILS ***/
    public function updateTeam(Request $request, $teamId)
    {
        // Retrieve the authenticated user
        $user = auth('sanctum')->user();
    
        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }
    
        // find team with requested teamId
        $team = Team::find($teamId);
    
        // return if no team found
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found !"
            ], 404);
        }
    
        // check if team creator is requesting 
        if ($user->id !== $team->team_creator_id) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to update this team details."
            ], 403);
        }
    
        // validate input request
        $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'team_url' => 'nullable|url',
            'team_handle' => [
                'nullable',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/',
                'max:255',
                // Add a unique rule for team_handle except the current team ID
                Rule::unique('teams')->ignore($teamId),
            ],
            'team_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'adminOnlyInvite' => 'boolean',
        ]);
    
        // update the data as per input
        $updateData = [];
    
        if ($request->filled('name')) {
            $updateData['name'] = $request->name;
        }
    
        if ($request->filled('description')) {
            $updateData['description'] = $request->description;
        }
    
        if ($request->filled('team_url')) {
            $updateData['team_url'] = $request->team_url;
        }
    
        if ($request->filled('team_handle')) {
            $updateData['team_handle'] = $request->team_handle;
        }
    
        if ($request->hasFile('team_picture')) {
            // Delete the old team picture if it exists
            if ($team->team_picture) {
                Storage::disk('public')->delete($team->team_picture);
            }

            $teamPicture = $request->file('team_picture');
            $teamPicturePath = $teamPicture->store('team_pictures', 'public'); // Store the image
            $updateData['team_picture'] = $teamPicturePath;
        }
        
        if ($request->filled('adminOnlyInvite')) {
            $updateData['adminOnlyInvite'] = $request->adminOnlyInvite;
        }
    
        $team->update($updateData);
    
        return response()->json([
            'status' => 200,
            'message' => 'Team updated successfully!',
            'team' => $team,
        ], 200);
    }


    /*** FUNCTION TO DELETE TEAM ***/
    public function deleteTeam($teamId)
    {
        // Retrieve the authenticated user
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // find team with requested teamId
        $team = Team::find($teamId);
    
        // return if no team found with requested teamId
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found !"
            ], 404);
        }

        // find that requesting user is owner or not
        if ($user->id !== $team->team_creator_id ) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to delete this team details."
            ], 403);
        }

        // Delete team image if it exists
        if ($team->team_picture) {
            // Delete the image file from the storage
            Storage::disk('public')->delete($team->team_picture);
        }

        // delete team 
        $team->delete();

        // response after succesful team deletion
        return response()->json([
            'status' => 200,
            'message' => 'Team deleted successfully!',
        ], 200);
    }



    /*** FUNCTION TO GET DETAILS OF SPECIFIC TEAM ***/
    public function showTeamById($teamId)
    {
        // find team with requested teamId
        $team = Team::find($teamId);
    
        // return if no team found with requested ID
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found !"
            ], 404);
        }

        // return team details of requested Id
        return response()->json([
            'status' => 200,
            'team' => $team,
        ], 200);
    }



    /*** FUNCTION TO DISPLAY ALL TEAMS OF USER SPECIFIC ***/
    public function indexAllTeams()
    {
        // Retrieve the authenticated user
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // find all team of user
        $teams = Team::where('team_creator_id', $user->id)->get();

        // return all teams of user
        return response()->json([
            'status' => 200,
            'teams' => $teams,
        ], 200);
    }



    /*** FUNCTION TO INVITE USER TO TEAM ***/
    public function inviteUser(Request $request, $teamId)
    {
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // Find the team
        $team = Team::find($teamId);
        
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found!"
            ], 404);
        }

        // Check if the user is a member of the team and has the role of owner
        if (!$this->isTeamAdminOrOwner($user->id, $teamId)) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to invite users to this team."
            ], 403);
        }

        // Validate request
        $request->validate([
            'username_or_email' => 'required|string',
            'role' => 'required|string|in:admin,member',
            'invite_message' => 'nullable|string',
        ]);

        // Find the user by username or email
        $invitedUser = User::where('username', $request->username_or_email)
                            ->orWhere('email', $request->username_or_email)
                            ->first();

        if (!$invitedUser) {
            return response()->json([
                'status' => 404,
                'message' => "User not found."
            ], 404);
        }

        // Check if the user is already a member of the team
        $existingMember = TeamMember::where('team_id', $teamId)
                                    ->where('user_id', $invitedUser->id)
                                    ->exists();

        if ($existingMember) {
            return response()->json([
                'status' => 400,
                'message' => "User is already a member of the team."
            ], 400);
        }

        // Generate a unique link for the invitation
        $teamInviteLink = Str::random(32);

        // Store the invite link in the database
        TeamInviteLink::create([
            'team_id' => $teamId,
            'link' => $teamInviteLink,
            'invite_message' => $request->invite_message,
            'invited_by' => $user->id,
            'role' => $request->role,
        ]);

        // Send email invitation
        $invitedUser->notify(new TeamInvitationNotification(url("/teams/$teamId/join?teamInviteLink=$teamInviteLink"), $request->role));

        return response()->json([
            'status' => 200,
            'message' => 'User invited to the team successfully!',
        ], 200);
    }



    /*** FUNCTION TO MANAGE TEAM MEMBER ***/

    public function manageTeamMember(Request $request, $teamId, $userId)
    {
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found!"
            ], 404);
        }

        // Find the role of the requesting user
        $requestingUserRole = TeamMember::where('team_id', $teamId)
                                        ->where('user_id', $user->id)
                                        ->value('role');

        // Find that requesting user is in team or not
        if (!$requestingUserRole) {
            return response()->json([
                'status' => 404,
                'message' => "You are not a member of the team."
            ], 404);
        }

        // Find the role of the target user
        $targetUserRole = TeamMember::where('team_id', $teamId)
                                    ->where('user_id', $userId)
                                    ->value('role');

        // Find the team member
        if (!$targetUserRole) {
            return response()->json([
                'status' => 404,
                'message' => "Target User is not a member of the team."
            ], 404);
        }


        // Determine permissions based on the requesting user's role
        switch ($requestingUserRole) {
            case 'owner':
                // Owner can change role of admin and member
                if ($request->action === 'change_role' && $targetUserRole !== 'owner') {
                    // Perform the action to change role
                    $request->validate([
                        'role' => 'required|in:admin,member'
                    ]);
                    $teamMember = TeamMember::where('team_id', $teamId)
                                            ->where('user_id', $userId)
                                            ->first();
                    $teamMember->update(['role' => $request->role]);

                    return response()->json([
                        'status' => 200,
                        'message' => 'Team member updated successfully!',
                    ], 200);

                } elseif ($request->action === 'remove_member') {
                    // Owner can remove admin and member (except themselves)
                    if ($userId == $user->id) {
                        return response()->json([
                            'status' => 403,
                            'message' => "You cannot remove yourself as the owner."
                        ], 403);
                    }

                    TeamMember::where('team_id', $teamId)
                            ->where('user_id', $userId)
                            ->delete();

                    return response()->json([
                        'status' => 200,
                        'message' => 'User Removed successfully from team!',
                    ], 200);

                } else {
                    // Unauthorized action
                    return response()->json([
                        'status' => 403,
                        'message' => "You do not have permission to perform this action."
                    ], 403);
                }
                break;
            case 'admin':
                // Admin can change role of member
                if ($request->action === 'change_role' && $targetUserRole === 'member') {
                    // Perform the action to change role
                    $request->validate([
                        'role' => 'required|in:admin,member'
                    ]);

                    $teamMember = TeamMember::where('team_id', $teamId)
                                            ->where('user_id', $userId)
                                            ->first();

                    $teamMember->update(['role' => $request->role]);

                    return response()->json([
                        'status' => 200,
                        'message' => 'Team member updated successfully!',
                    ], 200);

                } elseif ($request->action === 'remove_member') {
                    // Admin can remove themselves or member
                    if ($userId == $user->id || $targetUserRole === 'member') {
                        TeamMember::where('team_id', $teamId)
                                ->where('user_id', $userId)
                                ->delete();

                        return response()->json([
                            'status' => 200,
                            'message' => 'User Removed successfully from team!',
                        ], 200);
                    } else {
                        return response()->json([
                            'status' => 403,
                            'message' => "You do not have permission to perform this action."
                        ], 403);
                    }
                } else {
                    // Unauthorized action
                    return response()->json([
                        'status' => 403,
                        'message' => "You do not have permission to perform this action."
                    ], 403);
                }
                break;
            case 'member':

                if ($request->action === 'remove_member' && $userId == $user->id) {
                    TeamMember::where('team_id', $teamId)
                            ->where('user_id', $userId)
                            ->delete();
                        
                    return response()->json([
                        'status' => 200,
                        'message' => 'You successfully left the team!',
                    ], 200);    
                } else {
                    return response()->json([
                        'status' => 403,
                        'message' => "You do not have permission to manage team members."
                    ], 403);
                }
                break;
        }

    }


    // Helper function to check if the user is an admin or owner of the team
    private function isTeamAdminOrOwner($userId, $teamId)
    {
        return TeamMember::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereIn('role', ['admin', 'owner'])
            ->exists();
    }


    /*** FUNCTION TO CREATE INVITE LINK FOR TEAM ***/

    public function createTeamInviteLink(Request $request, $teamId)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // Return if user not registered
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // Find the team
        $team = Team::find($teamId);

        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found!"
            ], 404);
        }

        // Check if the user is an admin or owner of the team
        if (!$this->isTeamAdminOrOwner($user->id, $teamId)) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to create invite links for this team."
            ], 403);
        }

        // Generate a unique link
        $teamInviteLink = Str::random(32);

        // Store the invite link in the database
        TeamInviteLink::create([
            'team_id' => $teamId,
            'link' => $teamInviteLink,
            'role' => 'member',
            'invited_by' => $user->id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Invite link created successfully!',
            'invite_link' => url("/teams/$teamId/join?teamInviteLink=$teamInviteLink"), // Provide the link to the user
        ], 200);
    }


    /*** FUNCTION TO JOIN TEAM VIA INVITE LINK ***/

    public function joinTeamViaLink(Request $request, $teamId, $teamInviteLink)
    {
        $user = auth('sanctum')->user();

        // Return if user not registered
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // Find the team
        $team = Team::find($teamId);
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found!"
            ], 404);
        }

        // Find the invite link
        $inviteLink = TeamInviteLink::where('team_id', $teamId)
            ->where('link', $teamInviteLink)
            ->first();

        if (!$inviteLink) {
            return response()->json([
                'status' => 404,
                'message' => "Invalid or expired invite link."
            ], 404);
        }

        // Check if the user is already a member of the team
        $existingMember = TeamMember::where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();

        if ($existingMember) {
            return response()->json([
                'status' => 400,
                'message' => "You are already a member of this team."
            ], 400);
        }

        // Add the user to the team as a member
        TeamMember::create([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'username' => $user->username, // Store the username
            'role' => $inviteLink->role,
        ]);

        // Delete the invite link
        $inviteLink->delete();

        return response()->json([
            'status' => 200,
            'message' => 'You have successfully joined the team!',
        ], 200);
    }


}
