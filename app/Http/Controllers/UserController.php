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

class UserController extends Controller
{
    /***  Function for USER registeration ***/

    public function register(Request $request){
        //request validtion
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed',
            'tc' => 'required',
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
            'tc' => json_decode($request->tc),
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
            'message' => 'Registeration Successful , Please check your mail box to verify email address',
            'status'=>'success'
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
                'message' => 'Login Successful',
                'status'=>'success'
            ], 200);
        
        }

        // response on wrong credentials
        return response([
            'message' => 'The provided credentials are incorrect',
            'status' => 'failed'
        ], 401);
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


    /*** Function For Getting Data of logged in USER ***/

    public function logged_user(Request $request){
        $loggeduser = auth()->user(); // validate user logged in or not

        // Response for displaying user data
        return response([
            'user' => $loggeduser,
            'message' => 'User Details',
            'status'=>'success'
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

