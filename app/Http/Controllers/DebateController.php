<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use App\Models\Vote;
use App\Models\User;
use App\Models\Thanks;
use App\Models\Tag;
use App\Models\Bookmark;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\FileUploadService;
use App\Models\DebateComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;


class DebateController extends Controller
{
    /** CLASS TO DISPLAY ALL DEBATES ***/

    public function getalldebates()
    {
        $debates = Debate::whereNull('parent_id')->get();

        // Transform the debates into a simplified structure
        $transformedDebates = $debates->map(function ($debate) {
            return $this->transformMainDebate($debate);
        });

        return response()->json([
            'status' => 200,
            'mainDebates' => $transformedDebates,
        ], 200);
    }

    private function transformMainDebate($debate)
    {
        $debate->tags = json_decode($debate->tags);

        return $debate;
    }


    /** CLASS TO CREATE DEBATE ***/

    public function storetodb(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:191',
            'thesis' => 'required|string|max:191',
            'tags' => 'string|max:191',
            'backgroundinfo' => 'string|max:191',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048', // Add this line for file validation
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }
    
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    

        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = FileUploadService::upload($file, 'debate_images');
        }

        $tagsArray = !empty($request->tags) ? explode(',', $request->tags) : null; // Convert tags to an array or set to null if not provided

        if (!empty($tagsArray)) {
            foreach ($tagsArray as $tag) {
                $this->addTagIfNotExists($tag);
            }
        }

        $storevar = debate::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'thesis' => $request->thesis,
            'tags' => !empty($tagsArray) ? json_encode($tagsArray) : null,
            'backgroundinfo' => $request->backgroundinfo,
            'image' => $filePath,
            'imgname' => $filePath ? pathinfo($filePath, PATHINFO_FILENAME) : null,
            'isDebatePublic' => $request->isDebatePublic,
            'isType' => $request->isType,
            'voting_allowed' => $request->voting_allowed ?? false,
        ]);

        // Update user comments & contributions in users table
        $user->total_claims += 1; // Increment total claims
        $user->total_contributions += 1; // Increment total contributions
        $user->save();

        if ($storevar) {
            return response()->json([
                'status' => 200,
                'message' => 'Debate topic created Successfully',
            ], 200);
        } else {
            return response()->json([
                'status' => 500,
                'message' => "OOPS! Something went wrong!",
            ], 500);
        }
    }

    // Method to check if the tag exists in the tags table and create it if not

    private function addTagIfNotExists($tag)
    {
        $existingTag = Tag::where('tag', $tag)->first();

        if (!$existingTag) {
            Tag::create(['tag' => $tag]);
        }
    }



    /** CLASS TO FETCH ALL TAGS **/

    public function getAllTags()
    {
        $tags = Tag::all();
    
        $transformedTags = $tags->map(function ($tag) {
            return [
                'name' => $tag->tag,
                'image' => $tag->image ? asset('storage/' . $tag->image) : null,
            ];
        });
    
        return response()->json([
            'status' => 200,
            'tags' => $transformedTags,
        ], 200);
    }
    

    /** CLASS TO FETCH DEBATES BY TAG **/

    public function getDebatesByTag($tag)
    {
        $debates = Debate::where(function($query) use ($tag) {
            $tagArray = json_encode($tag);
            $query->where('tags', 'like', '%"'. $tag .'"%'); // Look for exact match within the JSON string
            $query->orWhere(function($query) use ($tagArray) {
                $query->whereJsonContains('tags', $tagArray); // Look for match within the JSON array
            });
        })->get();

        if ($debates->count() > 0) {
            // Decode the JSON-encoded tags for each debate
            $debates->transform(function ($debate) {
                $debate->tags = json_decode($debate->tags);
                return $debate;
            });

            return response()->json([
                'status' => 200,
                'debates' => $debates,
            ], 200);
        } else {
            return response()->json([
                'status' => 404,
                'message' => 'No Debates found for the specified tag',
            ], 404);
        }
    }


    /** CLASS TO EDIT DEBATE BY ID ***/

    public function editdebateindb($id)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        $findbyidvar = Debate::find($id);
    
        if (!$findbyidvar) {
            return response()->json([
                'status' => 404,
                'message' => "No Such Topic Found!"
            ], 404);
        }
    
        // Check if the authenticated user is the owner of the debate
        if ($user->id !== $findbyidvar->user_id) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to edit this debate."
            ], 403);
        }
    
        return response()->json([
            'status' => 200,
            'Debate' => $findbyidvar
        ], 200);
    }
    


    /** CLASS TO UPDATE DEBATE BY ID ***/

    public function updatedebate(Request $request, int $id)
    {

            $user = auth('sanctum')->user(); // Retrieve the authenticated user

            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'message' => 'You are not authorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'string|max:191',
                'thesis' => 'string|max:191',
                'tags' => 'string|max:191',
                'backgroundinfo' => 'string|max:191',
                'file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048', // Add this line for file validation
            ]);
        
            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'errors' => $validator->messages()
                ], 422);
            }
        
            $storevar = debate::find($id);
            if (!$storevar) {
                return response()->json([
                    'status' => 404,
                    'message' => "No Such Topic Found!"
                ], 404);
            }

            // Check if the authenticated user is the owner of the debate
            if ($user->id !== $storevar->user_id) {
                return response()->json([
                    'status' => 403,
                    'message' => "You do not have permission to update this debate."
                ], 403);
            }
    
            if ($storevar) {
                // Delete existing file
                FileUploadService::delete($storevar->image);
        
                // Upload new file if provided
                $filePath = null;
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $filePath = FileUploadService::upload($file, 'debate_images');
                }
        
                // Convert tags to an array or set to null if not provided
                $tagsArray = !empty($request->tags) ? explode(',', $request->tags) : null; 

                if (!empty($tagsArray)) {
                    foreach ($tagsArray as $tag) {
                        $this->addTagIfNotExists($tag);
                    }
                }
                
                $storevar->update([
                    'title' => $request->title,
                    'thesis' => $request->thesis,
                    'tags' => !empty($tagsArray) ? json_encode($tagsArray) : null,
                    'backgroundinfo' => $request->backgroundinfo,
                    'image' => $filePath,
                    'imgname' => $filePath ? pathinfo($filePath, PATHINFO_FILENAME) : null,
                    'isDebatePublic' => $request->isDebatePublic,
                    'isType' => $request->isType,
                    'voting_allowed' => $request->voting_allowed ?? false,
                ]);
        
                return response()->json([
                    'status' => 200,
                    'message' => 'Debate topic Updated Successfully'
                ], 200);
            } 
    }

        
    /** CLASS TO DELETE DEBATE ***/

    public function destroydebate($id)
    {
            // Find the debate by ID with its pros and cons
            $debate = Debate::with(['pros', 'cons'])->find($id);

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


    /*** CLASS TO SELECT PROS SIDE ***/
    
    public function addProsChildDebate(Request $request, int $parentId)
    {
        return $this->addChildDebate($request, $parentId, 'pros');
    }

    /*** CLASS TO SELECT CONS SIDE ***/

    public function addConsChildDebate(Request $request, int $parentId)
    {
        return $this->addChildDebate($request, $parentId, 'cons');
    }
    

    /*** CLASS TO ADD CHILD DEBATE BY SELECTING SIDE (PROS/CONS) ***/

    private function addChildDebate(Request $request, int $parentId, string $side)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:191',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }
    
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    

        // Find the parent debate
        $parentDebate = Debate::find($parentId);
    
        if (!$parentDebate) {
            return response()->json([
                'status' => 404,
                'message' => "Parent Debate not found!"
            ], 404);
        }

        // Determine the root_id for the child debate
        $rootId = $parentDebate->root_id ?? $parentId;
    
        // Add the child debate with the specified side
        $childDebate = Debate::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'side' => $side,
            'parent_id' => $parentId,
            'root_id' => $rootId,
            'voting_allowed' => $parentDebate->voting_allowed ?? false, // Inherit voting_allowed from parent debate
        ]);
    
        // Update user comments & contributions in users table
        $user->total_claims += 1; // Increment total claims
        $user->total_contributions += 1; // Increment total contributions
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'Child Debate created Successfully',
            'childDebate' => $childDebate,
        ], 200);
    }
    


    /*** CLASS TO DISPLAY DEBATE BY ID ***/

    public function getDebateByIdWithHierarchy($id)
    {
        // Find the specified debate by ID with its pros and cons
        $debate = Debate::with(['pros', 'cons'])->find($id);

        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!'
            ], 404);
        }

        // Transform the debate into a nested structure
        $transformedDebate = $this->transformDebate($debate);

        return response()->json([
            'status' => 200,
            'debate' => $transformedDebate,
        ], 200);
    }

    private function transformDebate($debate)
    {
        $debate->tags = json_decode($debate->tags);

        if ($debate->pros) {
            $debate->pros->transform(function ($pro) {
                return $this->transformDebate($pro);
            });
        }

        if ($debate->cons) {
            $debate->cons->transform(function ($con) {
                return $this->transformDebate($con);
            });
        }

        return $debate;
    }


    /*** CLASS TO VOTE DEBATES ***/

    public function vote(Request $request, int $debateId)
    {
        $validator = Validator::make($request->all(), [
            'vote' => 'required|integer|between:1,5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }

        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
        

        $debate = Debate::find($debateId);

        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => "Debate not found!"
            ], 404);
        }

        if (!$debate->voting_allowed) {
            return response()->json([
                'status' => 403,
                'message' => "Voting is not allowed for this debate."
            ], 403);
        }

        $vote = new Vote([
            'user_id' => $user->id,
            'vote' => $request->vote,
        ]);

        $debate->votes()->save($vote);

        // Increment total_votes column
        $debate->increment('total_votes');

        // Update user comments & contributions in users table
        $user->total_votes += 1; // Increment total votes
        $user->total_contributions += 1; // Increment total contributions
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'Vote recorded successfully',
        ], 200);
    }


    /*** CLASS TO GET VOTE COUNT ***/

    public function getVoteCounts($debateId)
    {
        $debate = Debate::find($debateId);

        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => "Debate not found!"
            ], 404);
        }

        $voteCounts = $debate->votes()
            ->select('vote', DB::raw('COUNT(*) as count'))
            ->groupBy('vote')
            ->get();

        // Create an array with vote counts for all possible votes (1 to 5)
        $allVoteCounts = array_fill_keys(range(1, 5), 0);

        // Merge the actual vote counts into the array
        $voteCounts->each(function ($voteCount) use (&$allVoteCounts) {
            $allVoteCounts[$voteCount->vote] = $voteCount->count;
        });

        return response()->json([
            'status' => 200,
            'voteCounts' => $allVoteCounts,
        ], 200);
    }


    /*** CLASS TO ADD COMMENTS IN DEBATE ***/

    public function addComment(Request $request, int $debateId)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages(),
            ], 422);
        }

        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        $debateComment = DebateComment::create([
            'user_id' => $user->id, // Assuming you have user authentication
            'debate_id' => $debateId,
            'comment' => $request->comment,
        ]);

        // Update user comments & contributions in users table
        $user->total_comments += 1; // Increment total commentss
        $user->total_contributions += 1; // Increment total contributions
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'Comment added successfully',
            'comment' => $debateComment,
        ], 200);
    }


    
    /*** CLASS TO EDIT COMMENT ***/

    public function editComment(Request $request, int $commentId)
    {
        $user = $request->user();
        $comment = DebateComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => 404,
                'message' => "Comment not found!"
            ], 404);
        }

        // Check if the user is the owner of the comment
        if ($comment->user_id !== $user->id) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to edit this comment."
            ], 403);
        }

        // Update the comment
        $comment->update([
            'comment' => $request->comment,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Comment edited successfully',
            'comment' => $comment,
        ], 200);
    }



    /*** CLASS TO HIDE COMMENT ***/

    public function hideComment(Request $request, int $commentId)
    {
        $user = $request->user();
        $comment = DebateComment::find($commentId);

        if (!$comment) {
            return response()->json([
                'status' => 404,
                'message' => "Comment not found!"
            ], 404);
        }

        // Check if the user is the owner of the comment
        if ($comment->user_id !== $user->id) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to hide this comment."
            ], 403);
        }

        // Soft delete the comment (mark it as hidden)
        $comment->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Comment hidden successfully',
        ], 200);
    }


    /*** CLASS TO RETRIVE COMMENTS LIST ***/

    public function getComments(int $debateId)
    {
        $comments = DebateComment::where('debate_id', $debateId)
            ->with('user:id,username') // Load user relationship to get user names
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 200,
            'comments' => $comments,
        ], 200);
    }


    /*** CLASS TO ADD THANKS TO AUTHOR IN DEBATE  ***/

    public function giveThanks(Request $request, int $debateId)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    
        $debate = Debate::find($debateId);

        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!',
            ], 404);
        }

        $debateOwner = $debate->user;
    
        // Check if the user has already given thanks
        $hasThanks = Thanks::where('user_id', $user->id)
            ->where('debate_id', $debate->id)
            ->exists();
    
        if ($hasThanks) {
            // Remove thanks
            Thanks::where('user_id', $user->id)
                ->where('debate_id', $debate->id)
                ->delete();
    
            // Decrement total_received_thanks
            $debateOwner->decrement('total_received_thanks');
            $message = 'Thanks removed successfully.';
        } else {
            // Add thanks
            Thanks::create([
                'user_id' => $user->id,
                'debate_id' => $debate->id,
            ]);
    
            // Increment total_received_thanks
            $debateOwner->increment('total_received_thanks');
            $message = 'Thanks recorded successfully.';
        }
    
        return response()->json([
            'status' => 200,
            'message' => $message,
            'total_received_thanks' => $debateOwner->total_received_thanks,
        ], 200);
    }


    /*** CLASS TO SEARCH DEBATE BY TAG, TITLE, THESIS ***/

    public function searchDebates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_query' => 'required|string|max:191',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages(),
            ], 422);
        }
    
        $searchQuery = $request->search_query;
    
        $mainDebates = Debate::whereNull('parent_id') // Only select main debates
            ->where(function ($query) use ($searchQuery) {
                $query->where('title', 'LIKE', '%' . $searchQuery . '%') // Use LIKE for case-insensitive search
                    ->orWhere('thesis', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere(function ($query) use ($searchQuery) {
                        // Case-insensitive search within JSON array
                        $query->where(DB::raw('JSON_UNQUOTE(tags)'), 'LIKE', '%' . $searchQuery . '%');
                    });
            })
            ->get();
    
        if ($mainDebates->count() > 0) {
            // Transform the main debates into a simplified structure
            $transformedMainDebates = $mainDebates->map(function ($mainDebate) {
                return $this->transformMainDebate($mainDebate);
            });
    
            return response()->json([
                'status' => 200,
                'debates' => $transformedMainDebates,
            ], 200);
        } else {
            return response()->json([
                'status' => 404,
                'message' => 'No Debates found for the specified search query',
            ], 404);
        }
    }

    
    /*** CLASS TO GET LIST OF TOP CONTRIBUTORS IN FEATURED PAGE ***/

    public function topContributors()
    {
        // Fetch top contributors in descending order based on total contributions
        $topContributors = User::orderByDesc('total_contributions')
            ->select('id', 'username', 'total_contributions')
            ->get();

        return response()->json([
            'status' => 200,
            'topContributors' => $topContributors,
        ], 200);
    }


    /*** CLASS TO GET OVERALL STATS FOR HOME PAGE ***/

    public function overallStats()
    {
        // Fetch overall contributions (sum of total contributions of all users)
        $overallContributions = (int) User::sum('total_contributions');
    
        // Fetch overall votes (sum of total votes of all users)
        $overallVotes = (int) Vote::count();
    
        // Fetch overall parent debates (total parent debates only excluding child debates)
        $overallParentDebates = (int) Debate::whereNull('parent_id')->count();
    
        // Fetch overall claims (sum of total claims from all users)
        $overallClaims = (int) User::sum('total_claims');
    
        return response()->json([
            'status' => 200,
            'overallContributions' => $overallContributions,
            'overallVotes' => $overallVotes,
            'overallParentDebates' => $overallParentDebates,
            'overallClaims' => $overallClaims,
        ], 200);
    }
    

    /*** CLASS TO BOOKMARK ON DEBATES ***/

    public function toggleBookmark(Request $request, int $debateId)
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        $debate = Debate::find($debateId);

        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found',
            ], 404);
        }

        $bookmark = Bookmark::where('user_id', $user->id)
                            ->where('debate_id', $debateId)
                            ->first();

        if ($bookmark) {
            // Debate already bookmarked, remove bookmark
            $bookmark->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Debate removed from bookmarks',
            ], 200);
        } else {
            // Bookmark the debate
            Bookmark::create([
                'user_id' => $user->id,
                'debate_id' => $debateId,
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Debate bookmarked successfully',
            ], 200);
        }
    }


   /*** CLASS TO GET LIST OF MY BOOKMARKED DEBATES OF SPECIFIC DEBATE ***/

   public function getBookmarkedDebates(Request $request, $debateId)
   {
       $user = $request->user(); // Get the authenticated user directly from the token
   
       if (!$user) {
           return response()->json([
               'status' => 401,
               'message' => 'Unauthorized Access'
           ], 401);
       }
   
       // Find the debate by ID
       $debate = Debate::find($debateId);
   
       if (!$debate) {
           return response()->json([
               'status' => 404,
               'message' => 'Debate not found',
           ], 404);
       }
   
       // Find the root debate ID
       $rootId = $debate->root_id ?? $debateId;
   
       // Retrieve all bookmarks within the hierarchy starting from the root debate
       $bookmarkedDebates = $user->bookmarkedDebates()
           ->where('root_id', $rootId)
           ->orWhereNull('root_id') // Include debates with null root_id
           ->get();
   
       return response()->json([
           'status' => 200,
           'bookmarkedDebates' => $bookmarkedDebates,
       ], 200);
   }
   
   


    /*** CLASS TO GET LIST OF MY CLAIMS OF SPECIFIC DEBATE ***/

    public function getClaimsByDebate(Request $request, $debateId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // Find the root debate ID
        $rootId = Debate::find($debateId)->root_id ?? $debateId;

        // Helper method to retrieve claims within a hierarchy
        $getClaimsRecursive = function ($debateId) use (&$getClaimsRecursive, $user) {
            $debate = Debate::find($debateId);

            if (!$debate) {
                return collect(); // Return an empty collection if the debate is not found
            }

            $claims = $debate->children()->where('user_id', $user->id)->get();

            foreach ($debate->children as $child) {
                $claims = $claims->merge($getClaimsRecursive($child->id));
            }

            return $claims;
        };

        // Get claims within the hierarchy
        $userClaims = $getClaimsRecursive($rootId);

        // Include the root debate in the result
        $rootDebate = Debate::find($rootId);
        if ($rootDebate && $rootDebate->user_id == $user->id) {
            $userClaims->push($rootDebate);
        }

        // Sort the collection by created_at in descending order (newer first)
        $userClaims = $userClaims->sortByDesc('created_at')->values()->all();

        return response()->json([
            'status' => 200,
            'userClaims' => $userClaims,
        ], 200);
    }
    

    /*** CLASS TO GET LIST OF CONTRIBUTIONS ON SPECIFIC DEBATE ***/

    public function getContributionsRecursive($debateId)
    {
        $user = auth('sanctum')->user();
    
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    
        // Find the root debate ID
        $rootId = Debate::find($debateId)->root_id ?? $debateId;
    
        // Helper method to retrieve contributions within a hierarchy
        $getContributionsRecursive = function ($debateId) use (&$getContributionsRecursive, $user) {
            $debate = Debate::find($debateId);
    
            if (!$debate) {
                return collect(); // Return an empty collection if the debate is not found
            }
    
            // Get user's contributions for this debate
            $contributions = [];
    
            // Get user's comments for this debate
            $comments = $debate->comments()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
    
            foreach ($comments as $comment) {
                $comment->type = 'comment';
                array_unshift($contributions, $comment); // Add at the beginning of the array
            }
    
            // Get user's votes for this debate
            $votes = $debate->votes()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
    
            foreach ($votes as $vote) {
                $vote->type = 'vote';
                array_unshift($contributions, $vote); // Add at the beginning of the array
            }
    
            // Get user's claims for this debate
            $claims = $debate->children()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
    
            foreach ($claims as $claim) {
                $claim->type = 'claim';
                array_unshift($contributions, $claim); // Add at the beginning of the array
            }
    
            // Get user's bookmark for this debate
            $bookmarked = $user->bookmarkedDebates()
                ->where('debate_id', $debateId)
                ->orderBy('created_at', 'desc')
                ->get();
    
            foreach ($bookmarked as $bookmark) {
                $bookmark->type = 'bookmark';
                array_unshift($contributions, $bookmark); // Add at the beginning of the array
            }
    
            // Recursively get contributions for child debates
            foreach ($debate->children as $child) {
                $childContributions = $getContributionsRecursive($child->id);
                $contributions = array_merge($contributions, $childContributions);
            }
    
            return $contributions;
        };
    
        // Get contributions within the hierarchy
        $contributions = $getContributionsRecursive($rootId);
    
        // Sort all contributions by time in descending order
        usort($contributions, function ($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
    
        return response()->json([
            'status' => 200,
            'contributions' => $contributions,
        ], 200);
    }
    


    /*** CLASS TO GET LIST OF COMMENTS IN SPECIFIC DEBATE ***/

    public function getCommentsByDebate($debateId)
    {
        $user = auth('sanctum')->user();
    
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    
        // Find the root debate ID
        $rootId = Debate::find($debateId)->root_id ?? $debateId;
    
        // Get user-specific comments within the hierarchy
        $userSpecificComments = DebateComment::whereHas('debate', function ($query) use ($rootId) {
                $query->where('root_id', $rootId)
                    ->orWhereNull('root_id'); // Include comments with null root_id
            })
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    
        return response()->json([
            'status' => 200,
            'userSpecificComments' => $userSpecificComments,
        ], 200);
    }    
}