<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\Debate;
use App\Models\User;
use App\Models\Team;

class TeamController extends Controller
{
    
    /*** FUNCTION TO CREATE A NEW TEAM ***/
    public function createTeam(Request $request)
    {
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Generate a URL-friendly team handle
        $teamHandle = Str::slug($request->name);

        $team = Team::create([
            'name' => $request->name,
            'team_handle' => $teamHandle,
            'team_creator_id' => $user->id, // Assign the current user id as the team creator
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Team created successfully!',
            'team' => $team,
        ], 201);
    }



    /*** FUNCTION TO UPDATE TEAM DETAILS ***/
    public function updateTeam(Request $request, $teamId)
    {
        $user = auth('sanctum')->user();
    
        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }
    
        $team = Team::find($teamId);
    
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found !"
            ], 404);
        }
    
        if ($user->id !== $team->team_creator_id) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to update this team details."
            ], 403);
        }
    
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
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        $team = Team::find($teamId);
    
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found !"
            ], 404);
        }

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

        $team->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Team deleted successfully!',
        ], 200);
    }



    /*** FUNCTION TO GET DETAILS OF SPECIFIC TEAM ***/
    public function showTeamById($teamId)
    {
        $team = Team::find($teamId);
    
        // return if not debate found with requested ID
        if (!$team) {
            return response()->json([
                'status' => 404,
                'message' => "No Team Found !"
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'team' => $team,
        ], 200);
    }



    /*** FUNCTION TO DISPLAY ALL TEAMS OF USER SPECIFIC ***/
    public function indexAllTeams()
    {
        $user = auth('sanctum')->user();

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        $teams = Team::where('team_creator_id', $user->id)->get();

        return response()->json([
            'status' => 200,
            'teams' => $teams,
        ], 200);
    }
}
