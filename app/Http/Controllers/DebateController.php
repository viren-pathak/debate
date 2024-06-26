<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use App\Models\Vote;
use App\Models\User;
use App\Models\Thanks;
use App\Models\Tag;
use App\Models\Bookmark;
use App\Models\DebateRole;
use App\Models\Review;
use App\Models\ReviewHistory;
use App\Models\DebateEditHistory;
use App\Models\SourceInDebate;
use App\Models\SharedLink;
use App\Models\SuggestedDebate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\FileUploadService;
use App\Models\DebateComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use App\Notifications\DebateInvitationNotification;



class DebateController extends Controller
{

/********************************************************************* 
* 
*  DEBATE RELATED METHODS
* 
*********************************************************************/


    /** CLASS TO CREATE DEBATE ***/

    public function storetodb(Request $request)
    {
        // validate th requested fields
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:191',
            'thesis' => 'required|string|max:191',
            'tags' => 'string|max:191',
            'backgroundinfo' => 'string|max:191',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048', // Add this line for file validation
        ]);
    
        // response if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }
    
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    
        // add image into debate databse if added into request
        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = FileUploadService::upload($file, 'debate_images');
        }

        $tagsArray = !empty($request->tags) ? explode(',', $request->tags) : null; // Convert tags to an array or set to null if not provided

        // Add tag to tags table if not exists
        if (!empty($tagsArray)) {
            foreach ($tagsArray as $tag) {
                $this->addTagIfNotExists($tag);
            }
        }


        // create debate and store data in database
        $storevar = Debate::create([
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

        // Store embedded links if found in the title
        $this->storeEmbeddedLinksForRoot($storevar->id, $request->title);

            // Log the edit history
        DebateEditHistory::create([
            'root_id' => $storevar->id,
            'debate_id' => $storevar->id,
            'create_user_id' => $user->id,
            'last_title' => $request->title,
        ]);  

        // Assign the role to the user
        $this->assignRole($user->id, $storevar->id, 'owner');

        // Update user comments & contributions in users table
        $user->total_claims += 1; // Increment total claims
        $user->total_contributions += 1; // Increment total contributions
        $user->save();     

        // response after successfull debate creation and if ay error found
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

    // Helper function to embed links within title
    private function storeEmbeddedLinksForRoot($debateId, $title)
    {
        // Regular expression to find links in the title
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';

        preg_match_all($pattern, $title, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $displayText = $match[1]; // Extract the display text
            $link = $match[2]; // Extract the link

            // Store the embedded link in the sources_in_debate table
            SourceInDebate::create([
                'root_id' => $debateId,
                'debate_id' => $debateId,
                'debate_title' => $title,
                'display_text' => $displayText,
                'link' => $link,
            ]);
        }
    }


    /** CLASS TO EDIT DEBATE BY ID ***/

    public function editdebateindb($id)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // return if user not registered
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // find debate by ID
        $findbyidvar = Debate::find($id);
    
        // return if not debate found with requested ID
        if (!$findbyidvar) {
            return response()->json([
                'status' => 404,
                'message' => "No Such Topic Found!"
            ], 404);
        }
    
        // Check if the authenticated user is the owner, editor, or creator of the debate
        $rootIdToCheck = $findbyidvar->root_id ?? $id; // Use root_id if available, otherwise use debateId

        if ($user->id !== $findbyidvar->user_id && !$this->isEditorOrCreator($user->id, $rootIdToCheck)) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to edit this debate."
            ], 403);
        }
    
        // return debate details if everything is fine
        return response()->json([
            'status' => 200,
            'Debate' => $findbyidvar,
            'EditHistory' => $findbyidvar->editHistory,
        ], 200);
    }
    

    /** CLASS TO UPDATE DEBATE BY ID ***/

    public function updatedebate(Request $request, int $id)
    {

            $user = auth('sanctum')->user(); // Retrieve the authenticated user

            // return if user not registered
            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'message' => 'You are not authorized'
                ], 401);
            }

            // validate all the fields in request by user
            $validator = Validator::make($request->all(), [
                'title' => 'string|max:191',
                'thesis' => 'string|max:191',
                'tags' => 'string|max:191',
                'backgroundinfo' => 'string|max:191',
                'file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048', // Add this line for file validation
            ]);
        
            // return if validation in fields does not match
            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'errors' => $validator->messages()
                ], 422);
            }
        
            // find debate by ID in request
            $storevar = debate::find($id);
            if (!$storevar) {
                return response()->json([
                    'status' => 404,
                    'message' => "No Such Topic Found!"
                ], 404);
            }

            // Check if the authenticated user is the owner, editor, or creator of the debate
            $rootIdToCheck = $storevar->root_id ?? $id; // Use root_id if available, otherwise use debateId

            if ($user->id !== $storevar->user_id && !$this->isEditorOrCreator($user->id, $rootIdToCheck)) {
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

                // Log the edit history
                DebateEditHistory::create([
                    'root_id' => $storevar->root_id ?? $storevar->id,
                    'debate_id' => $storevar->id,
                    'create_user_id' => $storevar->user_id,
                    'edit_user_id' => $user->id,
                    'last_title' => $storevar->getOriginal('title'),
                    'edited_title' => $request->title,
                ]);
                
                // update debate in DB if everything fine
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

        
                // return successful response after successful updates
                return response()->json([
                    'status' => 200,
                    'message' => 'Debate topic Updated Successfully',
                    'EditHistory' => $storevar->editHistory,
                ], 200);
            } 
    }

        
    /** CLASS TO DELETE DEBATE ***/

    public function destroydebate($id)
    {
            // Find the debate by ID with its pros and cons
            $debate = Debate::with(['pros', 'cons'])->find($id);

            // return if debate with requested ID does not available
            if (!$debate) {
                return response()->json([
                    'status' => 404,
                    'message' => "Debate not found!"
                ], 404);
            }

            // Delete the debate and its entire hierarchy
            $this->deleteDebateHierarchy($debate);

            // response after successful deletion 
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


    /*** CLASS TO ARCHIVE DEBATE ***/

    public function archiveDebate(Request $request, int $debateId)
    {
        // Find the debate
        $debate = Debate::find($debateId);

        // Return 404 if debate not found
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!',
            ], 404);
        }

        // Check if the authenticated user is the owner of the debate
        $user = auth('sanctum')->user();

        $rootIdToCheck = $debate->root_id ?? $debateId; // Use root_id if available, otherwise use debateId

        if (!$user || ($user->id !== $debate->user_id && !$this->isEditorOrCreator($user->id, $rootIdToCheck))) {
            return response()->json([
                'status' => 403,
                'message' => 'Unauthorized to archive this debate!',
            ], 403);
        }

        // Check if the user is trying to archive a root debate and is not the owner
        if ($debate->parent_id === null && $user->id !== $debate->user_id) {
            return response()->json([
                'status' => 403,
                'message' => 'Only the owner can archive the root debate!',
            ], 403);
        }


        // Archive the debate and its children
        $this->archiveDebateRecursive($debate);

        return response()->json([
            'status' => 200,
            'message' => 'Debate archived successfully!',
        ], 200);
    }

    private function archiveDebateRecursive($debate)
    {
        // Archive the current debate
        $debate->update(['archived' => true]);

        // Recursively archive its children
        foreach ($debate->children as $childDebate) {
            $this->archiveDebateRecursive($childDebate);
        }
    }


   /*** CLASS TO MARK DEBATE FOR REVIEW ***/

    public function markForReview(Request $request, int $debateId)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // Return if user not registered
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // Find the debate
        $debate = Debate::find($debateId);

        // Return 404 if debate not found
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!',
            ], 404);
        }

        $rootId = $debate->root_id ?? $debateId; // get root_id of requested debateId

        // Check if the authenticated user is the owner or an editor
        if ($user->id !== $debate->user_id && !$this->isEditorOrCreator($user->id, $debateId)) {
            return response()->json([
                'status' => 403,
                'message' => 'You need editor access to mark this debate for review!',
            ], 403);
        }

        // Check if the debate is already marked for review
        $existingReview = Review::where('debate_id', $debateId)->first();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'review' => 'required|in:Unsupported,Not a Claim,Unclear,Vulgar/Abusive,Duplicate,Unrelated,Move Elsewhere,More than one claim',
            'reason' => 'string|nullable',
        ]);

        // Return if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }

        if ($existingReview) {
            // If the debate is already marked for review, update the existing review
            $existingReview->update([
                'root_id' => $rootId,
                'mark_user_id' => $user->id,
                'review' => $request->review,
                'reason' => $request->reason,
            ]);
    
            // Log the review history
            ReviewHistory::create([
                'status' => 'mark',
                'root_id' => $rootId,
                'debate_id' => $debateId,
                'mark_user_id' => $user->id,
                'review' => $request->review,
                'reason' => $request->reason,
            ]);
    
            return response()->json([
                'status' => 200,
                'message' => 'Debate review updated successfully!',
            ], 200);
        }
    
        // If no existing review, create a new one
        Review::create([
            'mark_user_id' => $user->id,
            'root_id' => $rootId,
            'debate_id' => $debateId,
            'review' => $request->review,
            'reason' => $request->reason,
        ]);
    
        // Log the review history
        ReviewHistory::create([
            'status' => 'mark',
            'root_id' => $rootId,
            'debate_id' => $debateId,
            'mark_user_id' => $user->id,
            'review' => $request->review,
            'reason' => $request->reason,
        ]);
    
        return response()->json([
            'status' => 200,
            'message' => 'Debate marked for review successfully!',
        ], 200);
    }


    /*** CLASS TO UNMARK DEBATE FROM REVIEW ***/

    public function unmarkFromReview(Request $request, int $debateId)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // Return if user not registered
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // Find the debate
        $debate = Debate::find($debateId);

        // Return 404 if debate not found
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!',
            ], 404);
        }

        $rootId = $debate->root_id ?? $debateId; // get root_id of requested debateId

        // Check if the authenticated user is the owner or editor
        if ($user->id !== $debate->user_id && !$this->isEditorOrCreator($user->id, $debateId)) {
            return response()->json([
                'status' => 403,
                'message' => 'You need editor access to unmark this debate from review!',
            ], 403);
        }

        // Check if the debate is marked for review
        $existingReview = Review::where('debate_id', $debateId)->first();

        // If no existing review, return an error
        if (!$existingReview) {
            return response()->json([
                'status' => 400,
                'message' => 'This debate is not marked for review.',
            ], 400);
        }

        // Retrieve the last mark_user_id before deleting the review
        $lastMarkUserId = $existingReview->mark_user_id;

        // Validate the request
        $validator = Validator::make($request->all(), [
            'reason' => 'string|nullable',
        ]);

        // Return if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }

        // Delete the review from the database if everything's right
        $existingReview->delete();

        // Log the review history
        ReviewHistory::create([
            'status' => 'unmark',
            'root_id' => $rootId,
            'debate_id' => $debateId,
            'mark_user_id' => $lastMarkUserId,
            'unmark_user_id' => $user->id,
            'review' => $existingReview->review,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Debate unmarked from review successfully!',
        ], 200);
    }



    /** CLASS TO DISPLAY ALL DEBATES ***/

    public function getalldebates()
    {
        // Get all debates where parent_id is null (only return root debate)
        $debates = Debate::whereNull('parent_id')->get();

        // Transform the debates into a simplified structure
        $transformedDebates = $debates->map(function ($debate) {
            return $this->transformMainDebate($debate);
        })->filter(); // Remove null values from the collection

        // return all root debates
        return response()->json([
            'status' => 200,
            'mainDebates' => $transformedDebates,
        ], 200);
    }

    private function transformMainDebate($debate)
    {
        // explode tags from comma as array
        $debate->tags = json_decode($debate->tags);

            // Exclude archived debates
        if ($debate->archived) {
            return null; // Return an empty JSON object
        }

        return $debate;
    }


    /*** CLASS TO DISPLAY DEBATE BY ID WITHOUT ARCHIVED DEBATES ***/

    public function getDebateByIdWithHierarchy($id)
    {
        // Find the specified debate by ID with its pros and cons
        $debate = Debate::with(['pros', 'cons'])->find($id);

        // response if debate with requested ID did not found
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!'
            ], 404);
        }

        // Find embedded links associated with this debate
        $embeddedLinks = SourceInDebate::where('root_id', $id)->get();

        // Append embedded links to the debate object
        $debate->embedded_links = $embeddedLinks;

        // Transform the debate into a nested structure
        $transformedDebate = $this->transformDebate($debate);

        // response of successfull
        return response()->json([
            'status' => 200,
            'debate' => $transformedDebate,
        ], 200);
    }

    private function transformDebate($debate)
    {
        // explode tags from comma as array
        $debate->tags = json_decode($debate->tags);

        // Exclude archived debates
        if ($debate->archived) {
            return new \stdClass; // Return an empty JSON object
        }

        // check if debate has pros
        if ($debate->pros) {
            $debate->pros->transform(function ($pro) {
                return $this->transformDebate($pro);
            });
        }

        // check if debate has cons
        if ($debate->cons) {
            $debate->cons->transform(function ($con) {
                return $this->transformDebate($con);
            });
        }

        return $debate;
    }


    /*** CLASS TO DISPLAY ALL DEBATES WITH ARCHIVED DEBATES ***/

    public function getAllDebatesWithArchived($id)
    {
        // Find the specified debate by ID with its pros and cons
        $debate = Debate::with(['pros', 'cons'])->find($id);

        // response if debate with requested ID did not found
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!'
            ], 404);
        }

        // Transform the debate into a nested structure
        $transformedDebate = $this->transformArchiveDebate($debate);

        // response of successfull
        return response()->json([
            'status' => 200,
            'debate' => $transformedDebate,
        ], 200);
    }

    private function transformArchiveDebate($debate)
    {
        // explode tags from comma as array
        $debate->tags = json_decode($debate->tags);


        // check if debate has pros
        if ($debate->pros) {
            $debate->pros->transform(function ($pro) {
                return $this->transformArchiveDebate($pro);
            });
        }

        // check if debate has cons
        if ($debate->cons) {
            $debate->cons->transform(function ($con) {
                return $this->transformArchiveDebate($con);
            });
        }

        return $debate;
    }


    /*** CLASS TO DISPLAY DEBATE INTO SUNBURST CHART AND TREE HIERARCHY ***/

    public function getDebateForSunburstChart($id)
    {
        // Find the specified debate by ID with its pros and cons
        $debate = Debate::with(['pros', 'cons'])->find($id);

        // response if debate with requested ID did not found
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!'
            ], 404);
        }

        // Find embedded links associated with this debate
        $embeddedLinks = SourceInDebate::where('root_id', $id)->get();

        // Append embedded links to the debate object
        $debate->embedded_links = $embeddedLinks;

        // Exclude archived debates
        if ($debate->archived) {
            return response()->json([
                'status' => 200,
                'debate' => new \stdClass, // Return an empty JSON object
            ], 200);
        }

        // Transform the debate into a nested structure for sunburst chart
        $transformedDebate = $this->transformDebateForSunburst($debate);

        // response of successful
        return response()->json([
            'status' => 200,
            'debate' => $transformedDebate,
        ], 200);
    }

    private function transformDebateForSunburst($debate)
    {
        $transformedDebate = [
            'id' => $debate->id,
            'parent_id' => $debate->parent_id,
            'side' => $debate->side,
            'title' => $debate->title,
            'created_at' => $debate->created_at,
            'updated_at' => $debate->updated_at,
            'children' => []
        ];

        // Recursively transform pros
        foreach ($debate->pros as $pro) {
            $transformedDebate['children'][] = $this->transformDebateForSunburst($pro);
        }

        // Recursively transform cons
        foreach ($debate->cons as $con) {
            $transformedDebate['children'][] = $this->transformDebateForSunburst($con);
        }

        return $transformedDebate;
    }

    
    /** CLASS TO GET ALL SOURCE LINK WITHING DEBATE HIERARCHY **/

    public function getSources($rootId)
    {
        // Fetch sources based on the root_id
        $sources = SourceInDebate::where('root_id', $rootId)->select('link', 'debate_id', 'debate_title')->get();

        // Return the sources
        return response()->json([
            'status' => 200,
            'sources' => $sources,
        ], 200);
    }


    /** CLASS DOWNLOAD FILE CONTAINING ALL SOURCES LINKS WITHIN DEBATE HIERARCHY **/

    public function downloadSourcesLinksFile($rootId)
    {

        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // return if user not registered in site
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
        
        // Find the root debate
        $rootDebate = Debate::whereNull('root_id')->findOrFail($rootId);

        // Fetch all sources within the hierarchy based on the root_id
        $sources = SourceInDebate::where('root_id', $rootId)->get();

        // Initialize content with the root debate title
        $content = "Root Debate Title: {$rootDebate->title}\n\n";

        // Add numbered links within the hierarchy
        foreach ($sources as $index => $source) {
            $content .= ($index + 1) . ". {$source->link}\n";
        }

        // Generate the file name
        $fileName = "{$rootDebate->title}_{$rootDebate->id}_sources.txt";

        // Store the content in a .txt file
        Storage::put($fileName, $content);

        // Download the file
        $filePath = storage_path("app/{$fileName}");
        $headers = ['Content-Type: text/plain'];

        // Delete the file immediately after download
        return response()->download($filePath, $fileName, $headers)->deleteFileAfterSend(true);
    }


    /*** CLASS TO CREATE FILTER ON THE BASIS OF USER AND ACTIVITY ***/

    public function activityFilter(Request $request, $debateId)
    {
        $userId = $request->input('user_id');
        $activityType = $request->input('activity_type');

        // Find the root debate ID
        $rootId = Debate::find($debateId)->root_id ?? $debateId;

        // Get activities within the hierarchy
        $activities = $this->getActivitiesRecursive($rootId, $userId, $activityType);

        // Sort all activities by time in descending order
        usort($activities, function ($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });

        return response()->json([
            'status' => 200,
            'user_id' => $userId,
            'activity_type' => $activityType,
            'activities' => $activities,
        ], 200);
    }

    public function getActivitiesRecursive($debateId, $userId, $activityType)
    {
        // find debate by requested debateId
        $debate = Debate::find($debateId);

        if (!$debate) {
            return collect(); // Return an empty collection if the debate is not found
        }

        $activities = [];

        if ($activityType === 'comments') {
            // Retrieve all debates within the hierarchy
            $allDebates = Debate::where('root_id', $debateId)->orWhere('id', $debateId)->get();

            // Extract debate IDs from the collection
            $debateIds = $allDebates->pluck('id')->toArray();

            // Retrieve comments for the specified user and the extracted debate IDs
            $comments = DebateComment::whereIn('debate_id', $debateIds)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($comments as $comment) {
                $comment->type = 'comment';
                array_unshift($activities, $comment); // Add at the beginning of the array
            }
        } elseif ($activityType === 'debates') {
            // Filter debates based on user_id
            if ($debate->user_id == $userId) {
                $debate->type = 'debate';
                array_unshift($activities, $debate);
            }
        } elseif ($activityType === 'votes') {
            $votes = Vote::whereHas('debate', function ($query) use ($debateId) {
                $query->where('root_id', $debateId)
                    ->orWhereNull('root_id');
            })
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($votes as $vote) {
                $vote->type = 'vote';
                array_unshift($activities, $vote);
            }
        }

        foreach ($debate->children as $child) {
            $childActivities = $this->getActivitiesRecursive($child->id, $userId, $activityType);
            $activities = array_merge($activities, $childActivities);
        }

        return $activities;
    }


    /*** CLASS TO GENERATE SHARABLE LINKS FOR DEBATE ***/

    public function shareDebateLink(Request $request, int $debateId)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // Return if user not registered
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized'
            ], 401);
        }

        // Find the debate
        $debate = Debate::find($debateId);

        // Return 404 if debate not found
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!',
            ], 404);
        }

        // Check if the authenticated user is the owner or an editor
        if ($user->id !== $debate->user_id && !$this->isEditorOrCreator($user->id, $debateId)) {
            return response()->json([
                'status' => 403,
                'message' => 'You need editor access to share the debate link!',
            ], 403);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'enable_sharing' => 'required|boolean',
            'role' => 'required|string|in:viewer,suggestor,editor',
        ]);

        // Return if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }

        // Generate a unique link
        $link = Str::random(32);

        // Store the shared link in the database
        SharedLink::create([
            'debate_id' => $debateId,
            'link' => $link,
            'invited_by' => $user->id,
            'role' => $request->role,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Debate link shared successfully!',
            'shared_link' => url("/debates/$debateId/join?link=$link"), // Provide the link to the user
        ], 200);
    }


    /*** CLASS TO JOIN DEBATES BY SHARED LINKS ***/

    public function joinDebateViaLink(Request $request, string $link)
    {
        $user = auth('sanctum')->user(); 
    
        // Check if the link exists
        $sharedLink = SharedLink::where('link', $link)->first();
        if (!$sharedLink) {
            return response()->json([
                'status' => 404,
                'message' => 'Invalid or expired debate link.',
            ], 404);
        }
    
        // Check if the user has already joined this debate
        $existingRole = DebateRole::where('user_id', $user->id)
        ->where('root_id', $sharedLink->debate_id)
        ->exists();

        if ($existingRole) {
            // Delete the shared link from the database
            $sharedLink->delete();
            
            return response()->json([
                'status' => 400,
                'message' => 'You are already part of this debate.',
            ], 400);
        }
    
        // Check if the user is authorized
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'You are not authorized to join the debate.',
            ], 401);
        }
    
        $role = $sharedLink->role; // get role from shared link

        // Add the user to the debate roles table
        DebateRole::create([
            'user_id' => $user->id,
            'role' => $role,
            'root_id' => $sharedLink->debate_id,
        ]);

        // Delete the shared link from the database
        $sharedLink->delete();

        return response()->json([
            'status' => 200,
            'message' => 'You have successfully joined the debate!',
        ], 200);
    }
    

    /*** CLASS TO INVITE USER VIA USERNAME OR MAIL ***/
    
    public function inviteUser(Request $request, int $debateId)
    {
        $user = auth('sanctum')->user();
    
        // Retrieve the input data from the request
        $data = $request->validate([
            'username' => 'nullable|string|exists:users,username',
            'email' => 'nullable|string|email',
            'role' => 'required|string|in:viewer,suggestor,editor',
        ]);
    
        // Find the debate
        $debate = Debate::find($debateId);
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!',
            ], 404);
        }
    
        // Check if the authenticated user is the owner or an editor
        if ($user->id !== $debate->user_id && !$this->isEditorOrCreator($user->id, $debateId)) {
            return response()->json([
                'status' => 403,
                'message' => 'You need editor access to invite users to the debate!',
            ], 403);
        }
    
        // Send an invitation notification if email is provided
        if (isset($data['email']) && $data['email']) {
            // Find user by email
            $invitedUser = User::where('email', $data['email'])->first();
            if ($invitedUser) {
                // Check if the user is already part of the debate
                $existingRole = DebateRole::where('user_id', $invitedUser->id)
                                            ->where('root_id', $debateId)
                                            ->exists();
                if ($existingRole) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'The invited user is already part of this debate!',
                    ], 400);
                }

                // If user is not register genaretae
                $invitationToken = $this->generateInvitationToken($debateId, $user->id, $data['role']);
                $invitationLink = url("/debates/$debateId/join?link=$invitationToken");
                $invitedUser->notify(new DebateInvitationNotification($invitationLink, $data['role']));
            } else {
                // User with provided email does not exist
                return response()->json([
                    'status' => 404,
                    'message' => 'User with provided email does not exist!',
                ], 404);
            }
        } elseif (isset($data['username']) && $data['username']) {
            // Find user by username
            $invitedUser = User::where('username', $data['username'])->first();
            if ($invitedUser) {
                // Check if the user is already part of the debate
                $existingRole = DebateRole::where('user_id', $invitedUser->id)
                                            ->where('root_id', $debateId)
                                            ->exists();
                if ($existingRole) {
                    return response()->json([
                        'status' => 400,
                        'message' => 'The invited user is already part of this debate!',
                    ], 400);
                }

                // If user is not register genaretae
                $invitationToken = $this->generateInvitationToken($debateId, $user->id, $data['role']);
                $invitationLink = url("/debates/$debateId/join?link=$invitationToken");
                $invitedUser->notify(new DebateInvitationNotification($invitationLink, $data['role']));
            } else {
                // User with provided username does not exist
                return response()->json([
                    'status' => 404,
                    'message' => 'User with provided username does not exist!',
                ], 404);
            }
        } else {
            // Neither email nor username provided
            return response()->json([
                'status' => 422,
                'message' => 'Please provide either email or username to invite a user!',
            ], 422);
        }

    
        return response()->json([
            'status' => 200,
            'message' => 'Invitation email sent successfully!',
        ], 200);
    }
    
    // Method to generate and store invitation token
    private function generateInvitationToken(int $debateId, int $invitedById, string $role)
    {
        $invitationToken = Str::random(32);
        SharedLink::create([
            'debate_id' => $debateId,
            'link' => $invitationToken,
            'invited_by' => $invitedById,
            'role' => $role,
        ]);
        return $invitationToken;
    }
    
    
    

/********************************************************************* 
* 
*  CHILD DEBATE RELATED METHODS
* 
*********************************************************************/


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
    
        // response if validation failed
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }
    
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // return if user not registered or not valid user token
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    

        // Find the parent debate
        $parentDebate = Debate::find($parentId);
    
        // response if debate not found with requested ID
        if (!$parentDebate) {
            return response()->json([
                'status' => 404,
                'message' => "Parent Debate not found!"
            ], 404);
        }

        // Determine the root_id for the child debate
        $rootId = $parentDebate->root_id ?? $parentId;
    
        $role = $this->getUserRoleInDebate($user->id, $rootId);

        // If the user is not an owner, editor, or writer, suggest the debate instead of adding it directly
        if (!in_array($role, ['owner', 'editor', 'writer'])) {
            // Assign the role to the user
            $this->assignRole($user->id, $rootId, 'suggester');

            $suggestedDebate = SuggestedDebate::create([
                'user_id' => $user->id,
                'root_id' => $rootId,
                'parent_id' => $parentId,
                'title' => $request->input('title'),
                'side' => $side,
                'voting_allowed' => $parentDebate->voting_allowed ?? false,
            ]);

            // Store embedded links if found in the title
            $this->storeEmbeddedLinksForChild($rootId, $suggestedDebate->id, $request->title);
    
            // Return a response indicating the debate suggestion has been submitted
            return response()->json([
                'status' => 202,
                'message' => 'Your debate suggestion has been submitted for review.',
                'suggestedDebate' => $suggestedDebate,
            ], 202);

            // Assign the role to the user
            $this->assignRole($user->id, $rootId, 'suggester');
        }
        
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

        // Log the edit history
        DebateEditHistory::create([
            'root_id' => $rootId,
            'debate_id' => $childDebate->id,
            'create_user_id' => $user->id,
            'last_title' => $request->title,
        ]);  

        // Store embedded links if found in the title
        $this->storeEmbeddedLinksForChild($rootId, $childDebate->id, $request->title);

        // response after successfully creating child debate
        return response()->json([
            'status' => 200,
            'message' => 'Child Debate created Successfully',
            'childDebate' => $childDebate,
        ], 200);
    }

    // Helper function to embed links within title
    private function storeEmbeddedLinksForChild($rootId, $debateId, $title)
    {
        // Regular expression to find links in the title
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';

        preg_match_all($pattern, $title, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $displayText = $match[1]; // Extract the display text
            $link = $match[2]; // Extract the link

            // Store the embedded link in the sources_in_debate table
            SourceInDebate::create([
                'root_id' => $rootId,
                'debate_id' => $debateId,
                'debate_title' => $title,
                'display_text' => $displayText,
                'link' => $link,
            ]);
        }
    }

    
    /*** CLASS TO MODERATE SUGGESTED CHILD DEBATES ***/

    public function moderateSuggestedDebate(Request $request, int $suggestedDebateId)
    {
        // Retrieve the authenticated user
        $user = auth('sanctum')->user();

        // Return if user is not authenticated
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // Find the suggested debate
        $suggestedDebate = SuggestedDebate::find($suggestedDebateId);

        // Return if suggested debate not found with requested ID
        if (!$suggestedDebate) {
            return response()->json([
                'status' => 404,
                'message' => 'Suggested Debate not found!'
            ], 404);
        }

        $rootId = $suggestedDebate->parent->root_id ?? $suggestedDebate->parent_id;

        // Check if the user is an owner or editor
        if (!$this->isEditorOrCreator($user->id, $rootId)) {
            return response()->json([
                'status' => 403,
                'message' => 'You do not have permission to moderate suggested debates.'
            ], 403);
        }

        // Accept or delete the suggested debate based on the request
        if ($request->action === 'accept') {
            // Add the suggested debate as a child debate
            $childDebate = Debate::create([
                'user_id' => $suggestedDebate->user_id,
                'title' => $suggestedDebate->title,
                'side' => $suggestedDebate->side,
                'parent_id' => $suggestedDebate->parent_id,
                'root_id' => $rootId,
                'voting_allowed' => $suggestedDebate->voting_allowed,
            ]);
        
            // Find the suggester user
            $suggester = User::find($suggestedDebate->user_id);

            // Update suggester's claims & contributions in users table
            $suggester->total_claims += 1; // Increment total claims
            $suggester->total_contributions += 1; // Increment total contributions

            // Change suggester's role to writer
            $this->assignRole($suggester->id, $rootId, 'writer', true); 

            $suggester->save();

            // Log the edit history
            DebateEditHistory::create([
                'root_id' => $rootId,
                'debate_id' => $childDebate->id,
                'create_user_id' => $suggester->id,
                'last_title' => $childDebate->title, // Use the child debate's title
            ]);

            // Store embedded links if found in the title
            $this->storeEmbeddedLinksForChild($rootId, $childDebate->id, $childDebate->title);

            // Delete the suggested debate
            $suggestedDebate->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Suggested debate accepted and added as child debate.'
            ], 200);
        } elseif ($request->action === 'delete') {
            // Delete the suggested debate
            $suggestedDebate->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Suggested debate deleted successfully.'
            ], 200);
        } else {
            return response()->json([
                'status' => 400,
                'message' => 'Invalid action specified.'
            ], 400);
        }
    }

    /*** CLASS TO MOVE CHILD DEBATE TO OTHER PARENT DEBATE ***/
        
    public function moveChildDebate(Request $request, int $childDebateId)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'newParentId' => 'required|integer|exists:debate,id',
            'newSide' => 'required|in:pros,cons',
        ]);

        // response if validation failed
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }

        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // return if user not registered or not valid user token
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // Find the child debate
        $childDebate = Debate::find($childDebateId);

        // response if debate not found with requested ID
        if (!$childDebate) {
            return response()->json([
                'status' => 404,
                'message' => "Child Debate not found!"
            ], 404);
        }

        // Check if the provided debate is a root debate
        if ($childDebate->root_id === null) {
            return response()->json([
                'status' => 400,
                'message' => "You cannot move the root debate."
            ], 400);
        }

        // Check if the authenticated user is the owner, editor, or creator of the debate
        $rootIdToCheck = $childDebate->root_id ?? $id; // Use root_id if available, otherwise use debateId

        // Check if the authenticated user is the owner editor or creator of the child debate
        if ($user->id !== $childDebate->user_id && !$this->isEditorOrCreator($user->id, $rootIdToCheck)) {
            return response()->json([
                'status' => 403,
                'message' => "You are not allowed to move this child debate since you are not the creator."
            ], 403);
        }

        // Find the new parent debate
        $newParentDebate = Debate::find($request->query('newParentId'));

        // response if new parent debate not found with requested ID
        if (!$newParentDebate) {
            return response()->json([
                'status' => 404,
                'message' => "New Parent Debate not found!"
            ], 404);
        }

        // Check if the child debate and new parent debate belong to the same hierarchy (root_id)
        if ($childDebate->root_id !== ($newParentDebate->root_id ?? $newParentDebate->id)) {
            return response()->json([
                'status' => 400,
                'message' => "Cannot move the child debate to a different hierarchy!"
            ], 400);
        }

        // Update the child debate with the new parent and side
        $childDebate->parent_id = $request->query('newParentId');
        $childDebate->side = $request->query('newSide');
        $childDebate->save();

        // response after successfully moving child debate
        return response()->json([
            'status' => 200,
            'message' => 'Child Debate moved successfully',
            'childDebate' => $childDebate,
        ], 200);
    }



/********************************************************************* 
* 
*  TAG RELATED METHODS
* 
*********************************************************************/


    /** CLASS TO FETCH ALL TAGS **/

    public function getAllTags()
    {
        // get all tags list
        $tags = Tag::all();

        // get all tags with name and images
        $transformedTags = $tags->map(function ($tag) {
            return [
                'name' => $tag->tag,
                'image' => $tag->image,
            ];
        });
    
        // return after succesfull response
        return response()->json([
            'status' => 200,
            'tags' => $transformedTags,
        ], 200);
    }
    

    /** CLASS TO FETCH DEBATES BY TAG **/

    public function getDebatesByTag($tag)
    {
        // find debates by tag (without case sensitive)
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

            // return debate if found by tag or return else when not any debate with specific tag
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




/********************************************************************* 
* 
*  COMMENT RELATED METHODS
* 
*********************************************************************/


    /*** CLASS TO ADD COMMENTS IN DEBATE ***/

    public function addComment(Request $request, int $debateId)
    {

        // find debate by requested ID
        $debate = Debate::find($debateId);

        // Response if debate not found with requested ID
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => "Debate not found!"
            ], 404);
        }

        // Validate user input
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        // response if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages(),
            ], 422);
        }

        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // return if user not registered
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // create debate comment
        $debateComment = DebateComment::create([
            'user_id' => $user->id, // Assuming you have user authentication
            'debate_id' => $debateId,
            'comment' => $request->comment,
        ]);

        // Determine the root_id for the child debate
        $rootId = $debate->root_id ?? $debateId;

        // Assign the role to the user
        $this->assignRole($user->id, $rootId, 'suggester');

        // Update user comments & contributions in users table
        $user->total_comments += 1; // Increment total commentss
        $user->total_contributions += 1; // Increment total contributions
        $user->save();

        // response after successful comment addition
        return response()->json([
            'status' => 200,
            'message' => 'Comment added successfully',
            'comment' => $debateComment,
        ], 200);
    }


    
    /*** CLASS TO EDIT COMMENT ***/

    public function editComment(Request $request, int $commentId)
    {
        // retrive authorized user
        $user = $request->user();

        // find comment by requested ID
        $comment = DebateComment::find($commentId);

        // return if no comments found with requested ID
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

        // response after succesfull updation
        return response()->json([
            'status' => 200,
            'message' => 'Comment edited successfully',
            'comment' => $comment,
        ], 200);
    }



    /*** CLASS TO HIDE COMMENT ***/

    public function hideComment(Request $request, int $commentId)
    {
        // retrive authorized user
        $user = $request->user();

        // find comment by requested ID
        $comment = DebateComment::find($commentId);

        // return if no comment found by requested ID
        if (!$comment) {
            return response()->json([
                'status' => 404,
                'message' => "Comment not found!"
            ], 404);
        }

        $rootIdToCheck = $comment->debate->root_id ?? $comment->debate_id; // Use root_id if available, otherwise use debate_id

        // Check if the user is the owner of the comment
        if (
            !$user ||
            (
                $user->id !== $comment->user_id &&
                !$this->isEditorOrCreator($user->id, $rootIdToCheck)
            )
        ) {
            return response()->json([
                'status' => 403,
                'message' => "You do not have permission to hide this comment."
            ], 403);
        }

        // Soft delete the comment (mark it as hidden)
        $comment->delete();

        // return after successfully hiding comment
        return response()->json([
            'status' => 200,
            'message' => 'Comment hidden successfully',
        ], 200);
    }


    /*** CLASS TO RETRIVE COMMENTS LIST ***/

    public function getComments(int $debateId)
    {
        // find comments by debate ID
        $comments = DebateComment::where('debate_id', $debateId)
            ->with('user:id,username') // Load user relationship to get user names
            ->orderBy('created_at', 'asc')
            ->get();

        // return comments list on debate (return empty array when no comments)
        return response()->json([
            'status' => 200,
            'comments' => $comments,
        ], 200);
    }




/********************************************************************* 
* 
*  VOTE RELATED METHODS
* 
*********************************************************************/


    /*** CLASS TO VOTE DEBATES ***/

    public function vote(Request $request, int $debateId)
    {
        // validate user input
        $validator = Validator::make($request->all(), [
            'vote' => 'required|integer|between:1,5',
        ]);
    
        // response after validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }
    
        $user = auth('sanctum')->user(); // Retrieve the authenticated user
    
        // return if user is not authorized
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
        
        // find debate by requested ID
        $debate = Debate::find($debateId);
    
        // Response if debate not found with requested ID
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => "Debate not found!"
            ], 404);
        }
    
        // check if voting allowed or not on root debate
        if (!$debate->voting_allowed) {
            return response()->json([
                'status' => 403,
                'message' => "Voting is not allowed for this debate."
            ], 403);
        }
    
        // Check if the user has already voted on this debate
        $existingVote = Vote::where('user_id', $user->id)
                            ->where('debate_id', $debateId)
                            ->first();
    
        if ($existingVote) {
            // Update existing vote
            $existingVote->update(['vote' => $request->vote]);

            // Response after successful vote update
            return response()->json([
                'status' => 200,
                'message' => 'Vote updated successfully',
            ], 200);
        } else {
            // Add vote in debate
            $vote = new Vote([
                'user_id' => $user->id,
                'debate_id' => $debateId,
                'vote' => $request->vote,
            ]);
    
            // Determine the root_id for the child debate
            $rootId = $debate->root_id ?? $debateId;
    
            // Assign the role to the user
            $this->assignRole($user->id, $rootId, 'suggester');
    
            // Save vote in debate
            $debate->votes()->save($vote);
    
            // Increment total_votes column
            $debate->increment('total_votes');

            // Update user votes & contributions in users table
            $user->total_votes += 1; // Increment total votes
            $user->total_contributions += 1; // Increment total contributions
            $user->save();
        }
    
        // Response after successful voting
        return response()->json([
            'status' => 200,
            'message' => 'Vote recorded successfully',
        ], 200);
    }   


    /*** CLASS TO REMOVE VOTE ***/

    public function removeVote(Request $request, int $debateId)
    {
        // Retrieve the authenticated user
        $user = auth('sanctum')->user();

        // Return if user is not authenticated
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // Find debate by the requested ID
        $debate = Debate::find($debateId);

        // Return if debate is not found with the requested ID
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => "Debate not found!"
            ], 404);
        }

        // Check if voting is allowed for this debate
        if (!$debate->voting_allowed) {
            return response()->json([
                'status' => 403,
                'message' => "Voting is not allowed for this debate."
            ], 403);
        }

        // Check if the user has voted for this debate
        $existingVote = Vote::where('user_id', $user->id)
                            ->where('debate_id', $debateId)
                            ->first();

        // If user hasn't voted for this debate, return an error response
        if (!$existingVote) {
            return response()->json([
                'status' => 400,
                'message' => "You haven't voted for this debate."
            ], 400);
        }

        // Delete the user's vote from the database
        $existingVote->delete();

        // Decrement total_votes column in the debate table
        $debate->decrement('total_votes');

        // Decrement total_votes and total_contributions in the users table for the respective user
        $user->total_votes -= 1;
        $user->total_contributions -= 1;
        $user->save();

        // Return a success response
        return response()->json([
            'status' => 200,
            'message' => 'Vote removed successfully',
        ], 200);
    }


    /*** CLASS TO GET VOTE COUNT ***/

    public function getVoteCounts($debateId)
    {
        // find debate by requested ID
        $debate = Debate::find($debateId);

        // return if no debate fpound by requested ID
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => "Debate not found!"
            ], 404);
        }

        // get vote count from database
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


    /** CLASS TO CREATE USER PERSPECTIVE FOR VOTES **/

    public function votesPerspective(Request $request, int $debateId)
    {
        // validate user_id from request
        $userId = $request->input('user_id');

        // validate debateId from request
        $debate = Debate::find($debateId);

        // if no debate found with requested debateId
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => "Debate not found!"
            ], 404);
        }

        // Get all votes in the debate hierarchy, including votes for the root debate
        $votes = DB::table('votes')
            ->leftJoin('debate', 'votes.debate_id', '=', 'debate.id')
            ->where(function ($query) use ($debate) {
                $query->where('debate.id', $debate->id) // Votes for the current debate
                    ->orWhere('debate.root_id', $debate->id); // Votes for child debates
            })
            ->when($userId !== null, function ($query) use ($userId) {
                $query->where('votes.user_id', $userId);
            })
            ->select('votes.*')
            ->get();

        // Check if no votes should be displayed
        if ($request->has('no_votes') && $request->boolean('no_votes')) {
            return response()->json([
                'status' => 200,
                'votes' => [],
                'message' => 'No votes to display',
            ], 200);
        }

        // successfull respnse
        return response()->json([
            'status' => 200,
            'votes' => $votes,
        ], 200);
    }


    

/********************************************************************* 
* 
*  ROLE RELATED METHODS
* 
*********************************************************************/


    /*** CLASS TO ASSIGN ROLES TO USERS ON THE BASIS OF ACTIVITY ***/

    private function assignRole($userId, $rootId, $role, $isOwner = false)
    {
        // Define the role hierarchy
        $roleHierarchy = [
            'owner' => 5,
            'editor' => 4,
            'writer' => 3,
            'suggester' => 2,
            'viewer' => 1,
        ];

        // Check if the role already exists for the user and root
        $existingRole = DebateRole::where('user_id', $userId)
            ->where('root_id', $rootId)
            ->first();

        // If the role exists, update it; otherwise, create a new one
        if ($existingRole) {
            if ($isOwner) {
                // If the action is performed by the owner, update the role without hierarchy check
                $existingRole->update(['role' => $role]);
            } else {
                // If the action is part of an activity, update the role if the new role is superior or equal
                if ($roleHierarchy[$role] >= $roleHierarchy[$existingRole->role]) {
                    $existingRole->update(['role' => $role]);
                }
            }
        } else {
            // If the role does not exist, create a new one
            DebateRole::create([
                'user_id' => $userId,
                'root_id' => $rootId,
                'role' => $role,
            ]);
        }
    }

    /*** CLASS TO GET USERS ROLE IN DEBATE ***/

    private function getUserRoleInDebate($userId, $rootId)
    {
        $role = DebateRole::where('user_id', $userId)
                          ->where('root_id', $rootId)
                          ->value('role');

        return $role;
    }
    
    /*** CLASS TO CHANGE USER ROLE IN DEBATE HIERRARCHY BY OWNER ONLY ***/

    public function changeUserRole(Request $request, $debateId, $userId)
    {
        // Validate the request
        $request->validate([
            'role' => 'required|in:owner,editor,writer,suggester,viewer',
        ]);
    
        // Check if the authenticated user is the owner of the debate hierarchy
        $user = $request->user();

        // find is auth user is owner of hierarchy
        $isDebateOwner = DebateRole::where('user_id', $user->id)
            ->where('root_id', $debateId) // Use root_id instead of debate_id
            ->where('role', 'owner')
            ->exists();
    
        // return if requested user is not owner of debate hierarchy
        if (!$isDebateOwner) {
            return response()->json(['error' => 'You are not the owner of this debate hierarchy.'], 403);
        }
    
        // Determine the root_id for the child debate
        $debate = Debate::find($debateId);
        $rootId = $debate->root_id ?? $debateId; // find root_id of requested debateId
    
        // Update the user's role
        $this->assignRole($userId, $rootId, $request->query('role'), true);
    
        // return after successful updation
        return response()->json(['message' => 'User role updated successfully.']);
    }


    // Helper function to check if the user is an owner/ editor or creator of the debate
    private function isEditorOrCreator($userId, $debateId)
    {
        // Retrieve the root_id of the requested debateId
        $rootId = Debate::find($debateId)->root_id ?? $debateId;

        // check role is owner, editor or not
        return DebateRole::where('user_id', $userId)
            ->where('root_id', $rootId)
            ->whereIn('role', ['owner', 'editor']) 
            ->exists();
    }




/********************************************************************* 
* 
*  BOOKMARK RELATED METHODS
* 
*********************************************************************/


    /*** CLASS TO BOOKMARK ON DEBATES ***/

    public function toggleBookmark(Request $request, int $debateId)
    {
        // retrive authorized user
        $user = auth('sanctum')->user();

        // check user authorized or not
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }

        // find debate by requested ID
        $debate = Debate::find($debateId);

        // return if no debate found by requested ID
        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found',
            ], 404);
        }

        // bookmark debate by user ID and debate ID
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

            // return after succesfull excecution
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
   
       // return if user not authorized
       if (!$user) {
           return response()->json([
               'status' => 401,
               'message' => 'Unauthorized Access'
           ], 401);
       }
   
       // Find the debate by ID
       $debate = Debate::find($debateId);
   
       // return if debated with requested ID not found
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
   
        // return after successfull excecution
       return response()->json([
           'status' => 200,
           'bookmarkedDebates' => $bookmarkedDebates,
       ], 200);
   }
   



/********************************************************************* 
* 
*  USER RELATED METHODS
* 
*********************************************************************/


    /*** CLASS TO ADD THANKS TO AUTHOR IN DEBATE  ***/

    public function giveThanks(Request $request, int $debateId)
    {
        $user = auth('sanctum')->user(); // Retrieve the authenticated user

        // return if user not authorized
        if (!$user) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized Access'
            ], 401);
        }
    
        // find debate by debate ID
        $debate = Debate::find($debateId);

        if (!$debate) {
            return response()->json([
                'status' => 404,
                'message' => 'Debate not found!',
            ], 404);
        }

        // get owner of debate
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
    
        // return after successfull response
        return response()->json([
            'status' => 200,
            'message' => $message,
            'total_received_thanks' => $debateOwner->total_received_thanks,
        ], 200);
    }


    /*** CLASS TO GET LIST OF MY CLAIMS OF SPECIFIC DEBATE ***/

    public function getClaimsByDebate(Request $request, $debateId)
    {
        // retrive authorized user
        $user = $request->user();

        // check if user authorized or not
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

            // make child debate as claim
            $claims = $debate->children()->where('user_id', $user->id)->get();

            // merge child and parent together
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
        // retrive authorized user
        $user = auth('sanctum')->user();
    
        // check if user authorized or not
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
    
            // return all contributions
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
        // retrive authorized user
        $user = auth('sanctum')->user();
    
        // check if user is authorized or not
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
    
        // return after succesfull excecution
        return response()->json([
            'status' => 200,
            'userSpecificComments' => $userSpecificComments,
        ], 200);
    }    




/********************************************************************* 
* 
*  HOME PAGE RELATED METHODS
* 
*********************************************************************/


    /*** CLASS TO SEARCH DEBATE BY TAG, TITLE, THESIS ***/

    public function searchDebates(Request $request)
    {
        // validate user input
        $validator = Validator::make($request->all(), [
            'search_query' => 'required|string|max:191',
        ]);
    
        // return if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages(),
            ], 422);
        }
    
        // search words by user input
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
    
        // return response after search query excecution
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
            ->select('id', 'profile_picture', 'username', 'total_contributions')
            ->get();

        // return list of contributors
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
    
        // return all stats
        return response()->json([
            'status' => 200,
            'overallContributions' => $overallContributions,
            'overallVotes' => $overallVotes,
            'overallParentDebates' => $overallParentDebates,
            'overallClaims' => $overallClaims,
        ], 200);
    }
    
   
}