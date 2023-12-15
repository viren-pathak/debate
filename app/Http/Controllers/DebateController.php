<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\FileUploadService;



class DebateController extends Controller
{
    /** CLASS TO DISPLAY ALL DEBATES ***/

    public function getalldebates()
    {
        $debatevar = debate::all();
        
        // Convert tags from JSON string to array
        $debatevar->transform(function ($debate) {
            $debate->tags = json_decode($debate->tags);
            return $debate;
        });
    
        if($debatevar->count() > 0){
            return response()->json([
                'status' => 200,
                'debates' => $debatevar
            ],200);
        } else {
            return response()->json([
                'status' => 404,
                'Message' => 'No Records Found'
            ],404);
        }     
    }


    /** CLASS TO CREATE DEBATE ***/

    public function storetodb(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:191',
            'thesis' => 'required|string|max:191',
            'tags' => 'required|string|max:191',
            'backgroundinfo' => 'required|string|max:191',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048', // Add this line for file validation
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->messages()
            ], 422);
        }
    
        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = FileUploadService::upload($file, 'debate_images');
        }
    
        $tagsArray = explode(',', $request->tags); // Convert tags to an array
    
        $storevar = debate::create([
            'title' => $request->title,
            'thesis' => $request->thesis,
            'tags' => json_encode($tagsArray), // Convert the array to a JSON string before storing
            'backgroundinfo' => $request->backgroundinfo,
            'image' => $filePath,
            'imgname' => $filePath ? pathinfo($filePath, PATHINFO_FILENAME) : null,
            'isDebatePublic' => $request->isDebatePublic,
            'isType' => $request->isType
        ]);
    
        if ($storevar) {
            return response()->json([
                'status' => 200,
                'message' => 'Debate topic created Successfully'
            ], 200);
        } else {
            return response()->json([
                'status' => 500,
                'message' => "OOPS! Something went wrong!"
            ], 500);
        }
    }


    /** CLASS TO GET DEBATE BY ID ***/

    public function getbyid($id)
    {
        $findbyidvar = debate::find($id);
        if ($findbyidvar){
            return response()->json([
                'status' => 200,
                'Debate' => $findbyidvar
            ],200);
        }else{

            return response()->json([
                'status' => 404,
                'message' => "No Such Topic Found!" 
            ],404);
        }
    }


/** CLASS TO FETCH ALL TAGS **/

public function getAllTags()
{
    $tags = Debate::all()->pluck('tags')->flatMap(function ($tags) {
        return json_decode($tags);
    })->unique();

    return response()->json([
        'status' => 200,
        'tags' => $tags,
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
        $findbyidvar = debate::find($id);
        if ($findbyidvar){
            return response()->json([
                'status' => 200,
                'Debate' => $findbyidvar
            ],200);
        }else{

            return response()->json([
                'status' => 404,
                'message' => "No Such Topic Found!" 
            ],404);
        }
    }


        /** CLASS TO UPDATE DEBATE BY ID ***/

        public function updatedebate(Request $request, int $id)
        {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:191',
                'thesis' => 'required|string|max:191',
                'tags' => 'required|string|max:191',
                'backgroundinfo' => 'required|string|max:191',
                'file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048', // Add this line for file validation
            ]);
        
            if ($validator->fails()) {
                return response()->json([
                    'status' => 422,
                    'errors' => $validator->messages()
                ], 422);
            }
        
            $storevar = debate::find($id);
            if ($storevar) {
                // Delete existing file
                FileUploadService::delete($storevar->image);
        
                // Upload new file if provided
                $filePath = null;
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $filePath = FileUploadService::upload($file, 'debate_images');
                }
        
                // Convert tags to an array
                $tagsArray = explode(',', $request->tags);
        
                $storevar->update([
                    'title' => $request->title,
                    'thesis' => $request->thesis,
                    'tags' => json_encode($tagsArray),
                    'backgroundinfo' => $request->backgroundinfo,
                    'image' => $filePath,
                    'imgname' => $filePath ? pathinfo($filePath, PATHINFO_FILENAME) : null,
                    'isDebatePublic' => $request->isDebatePublic,
                    'isType' => $request->isType
                ]);
        
                return response()->json([
                    'status' => 200,
                    'message' => 'Debate topic Updated Successfully'
                ], 200);
            } else {
                return response()->json([
                    'status' => 404,
                    'message' => "No Such Topic Found!"
                ], 404);
            }
        }

        

        /** CLASS TO DELETE DEBATE ***/

    public function destroydebate($id)
    {
        $destroyvar = debate::find($id);
        if($destroyvar){

            $destroyvar ->delete();

            return response()->json([
                'status' => 200,
                'Debate' => "Debate topic Deleted Successfully"
            ],200);

        }else{

            return response()->json([
                'status' => 500,
                'message' => "OOPS! Something went wrong!No such ID Found"
            ],500);
        }
    }


}