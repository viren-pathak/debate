<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Debate;
use App\Models\Vote;
use App\Models\Tag;
use Illuminate\Support\Facades\Validator;
use App\FileUploadService;
use App\Models\DebateComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;


class AdminController extends Controller
{

    /*** FUNCTION TO GET ALL USERS LIST ***/

    public function getAllUsers()
    {
        // get list of all users
        $users = User::all();

        // return list of all users
        return response()->json($users);
    }



    /*** FUNCTION TO GET USER DETAILS BY USER ID ***/

    public function getUserDetails($userId)
    {
        // find user by requested ID
        $user = User::find($userId);

        // return if no user found with requested ID
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // return details of user if found with requested ID
        return response()->json($user);
    }



    /*** FUNCTION TO DELETE USER ***/

    public function deleteUser($userId)
    {
        // find user by requested ID
        $user = User::find($userId);

        // return if no user found by requested ID
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // delete user if available
        $user->delete();

        // response after successful deletion
        return response()->json(['message' => 'User deleted successfully']);
    }



    /*** FUNCTION GET ALL VOTE LIST ***/

    public function getAllVotes()
    {
        // get list of all votes
        $votes = Vote::with(['user:id,username', 'debate:id,title'])
            ->get()
            ->map(function ($vote) {
                return [
                    'id' => $vote->id,
                    'vote' => $vote->vote,
                    'user' => $vote->user,
                    'debate' => $vote->debate,
                    'created_at' => $vote->created_at,
                    'updated_at' => $vote->updated_at,
                ];
            });

        // response after successful excecution
        return response()->json([
            'status' => 200,
            'votes' => $votes,
        ], 200);
    }



    /*** FUNCTION TO DELETE VOTE ***/

    public function deleteVote($id)
    {
        // find vote by requested ID
        $vote = Vote::find($id);

        // return if no vote found by requested ID
        if (!$vote) {
            return response()->json([
                'status' => 404,
                'message' => 'Vote not found!',
            ], 404);
        }

        // Decrement total_votes column
        $vote->debate->decrement('total_votes');
        
        // Update user total_votes count
        $vote->user->decrement('total_votes');

        // Delete the vote
        $vote->delete();

        // return after succeful excecution
        return response()->json([
            'status' => 200,
            'message' => 'Vote deleted successfully',
        ], 200);
    }



    /*** FUNCTION TO GET ALL COMMENTS ***/

    public function getAllComments()
    {
        // get list of all comments
        $comments = DebateComment::with(['user:id,username', 'debate:id,title'])
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'user' => $comment->user,
                    'debate' => $comment->debate,
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                ];
        });

        // response after succesful execution
        return response()->json([
            'status' => 200,
            'comments' => $comments,
        ], 200);
    }



    /*** FUNCTION TO DELETE COMMENT ***/

    public function deleteComment($id)
    {
        // find comment by requested ID
        $comment = DebateComment::find($id);

        // return if no comment available by requested ID
        if (!$comment) {
            return response()->json([
                'status' => 404,
                'message' => 'Comment not found!',
            ], 404);
        }

        // Update user total_votes count
        $comment->user->decrement('total_comments');

        // Delete the vote
        $comment->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Comment deleted successfully',
        ], 200);
    }



    /*** FUNCTION TO GET ALL DEBATES LIST ***/

    public function getAllDebates()
    {
        // get all debates list
        $debates = Debate::get();

        // Transform the debates into a simplified structure
        $transformedDebates = $debates->map(function ($debate) {
            return $this->transformMainDebate($debate);
        });

        return response()->json([
            'status' => 200,
            'mainDebates' => $transformedDebates,
        ], 200);
    }

    // private function to explode tags from comma to array
    private function transformMainDebate($debate)
    {
        $debate->tags = json_decode($debate->tags);

        return $debate;
    }


    
    /** FUNCTION TO DELETE DEBATE ***/

    public function deleteDebate($id)
    {
            // Find the debate by ID with its pros and cons
            $debate = Debate::with(['pros', 'cons'])->find($id);
    
            // return if no debate available with requested ID
            if (!$debate) {
                return response()->json([
                    'status' => 404,
                    'message' => "Debate not found!"
                ], 404);
            }
    
            // Delete the debate and its entire hierarchy
            $this->deleteDebateHierarchy($debate);
    
            return response()->json([
                'status' => 200,
                'message' => "Debate topic and its hierarchy deleted successfully"
            ], 200);
    }
    
    // private function to delete debate by hierarchy
    private function deleteDebateHierarchy($debate)
    {
        // Recursively delete child debates (pros and cons)
        if ($debate->pros) {
            foreach ($debate->pros as $pro) {
                $this->deleteDebateHierarchy($pro);
            }
        }
    
        if ($debate->cons) {
            foreach ($debate->cons as $con) {
                $this->deleteDebateHierarchy($con);
            }
        }
    
        // Delete the current debate
        $debate->delete();
    }


    /*** FUNCTION TO GET OVERALL STATS FOR ADMIN DASHBOARD ***/

    public function getAllStats()
    {
        // Fetch overall users
        $overallUsers = (int) User::count();

        // Fetch overall parent debates (total parent debates only excluding child debates)
        $overallParentDebates = (int) Debate::whereNull('parent_id')->count();

        // Fetch overall child debates (total child debates only excluding child debates)
        $overallChildDebates = (int) Debate::where('parent_id')->count();

        // Fetch overall Comments (sum of total comments of all users)
        $overallComments = (int) DebateComment::count();

        // Fetch overall votes (sum of total votes of all users)
        $overallVotes = (int) Vote::count();
   
        // Fetch overall claims (sum of total claims from all users)
        $overallClaims = (int) User::sum('total_claims');

        // Fetch overall contributions (sum of total contributions of all users)
        $overallContributions = (int) User::sum('total_contributions');
    
    
        return response()->json([
            'status' => 200,
            'overallUsers' => $overallUsers,
            'overallParentDebates' => $overallParentDebates,
            'overallChildDebates' => $overallChildDebates,
            'overallComments' => $overallComments,
            'overallVotes' => $overallVotes,
            'overallClaims' => $overallClaims,
            'overallContributions' => $overallContributions,
        ], 200);
    }


    /*** FUNCTION TO ADD TAGS in database ***/
    public function addTag(Request $request)
    {
        // validate input
        $validator = Validator::make($request->all(), [
            'tag' => 'required|string|max:191',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        // return if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages(),
            ], 422);
        }
    
        $tag = Tag::whereRaw('LOWER(`tag`) = ?', [strtolower($request->tag)])->first(); // Case-insensitive check
    
        if ($tag) {
            // Tag already exists, return it without creating a new one
            return response()->json([
                'status' => 200,
                'message' => 'Tag already exists',
                'tag' => $tag,
            ], 200);
        }
    
        // add image into tag
        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = FileUploadService::upload($image, 'tag_images');
        }
    
        // create tag and store in DB
        $tag = Tag::create([
            'tag' => $request->tag,
            'image' => $imagePath,
        ]);
    
        // response after succesfull excecution
        return response()->json([
            'status' => 200,
            'message' => 'Tag created successfully',
            'tag' => $tag,
        ], 200);
    }
    

}
