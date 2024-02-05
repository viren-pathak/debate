<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Mail\Message;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /*** FUNCTION TO SEND RESET PASSWORD MAIL ***/

    public function send_reset_password_email(Request $request){

        // request email from user
        $request->validate([
            'email' => 'required|email',
        ]);

        // get email id from request
        $email = $request->email;

        // check Provided Email ID exis or not in db
        $user = User::where('email', $email)->first();

        if(!$user){
            // response if email not found in db
            return response([
                'message' => 'Email not Registered',
                'status' => 'failed'
            ], 404);
        }

        // Generate token
        $token = Str::random(60);

        //Saving token and email to password Reset Table
        PasswordReset::create([
            'email'=>$email,
            'token'=>$token,
            'created_at'=>Carbon::now()
        ]);

        //Sending EMail with Password Reset View
        Mail::send('resetpassword', ['token'=>$token], function(Message $message)use($email){
            $message->subject('Reset Your Password');
            $message->to($email);
        });
                                  
        // Response after successful email sent
        return response([
            'message' => 'Password Reset Mail sent... Please check Your Mail Box',
            'status'=>'success'
        ], 200);

    }


    /*** FUNCTION TO UPDATE PASSWORD IN DB AFTER RESET  ***/

    public function reset_password(Request $request, $token){
        // Delete Token After 30 minutes
        $formatted = Carbon::now()->subMinutes(30)->toDateTimeString();
        PasswordReset::where('created_at', '<=', $formatted)->delete();

        // request updated password
        $request->validate([
            'password' => 'required|confirmed',
        ]);

        //Check user token and reset token in DB same or not
        $passwordreset = PasswordReset::where('token', $token)->first();

        // Check token valid or not
        if(!$passwordreset){
            // response if token not valid
            return response([
                'message' => 'Token is Invalid or expired',
                'status' => 'failed'
            ], 404);
        }

        // Match User email with token email
        $user = User::where('email', $passwordreset->email)->first();

        // Update password after reset
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the token after resetting password
        PasswordReset::where('email', $user->email)->delete();

        // Response after successful Password reset
        return response([
            'message' => 'Password Reset Succesful',
            'status'=>'success'
        ], 200);
    }

}
