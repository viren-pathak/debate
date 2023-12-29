<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function verify(Request $request, $token)
    {
        $user = User::where('verification_token', $token)->first();

        if (!$user) {
            /*
            *
            FOR LOCAL HOST
            *
                // invalid token
                return response([
                    'message' => 'Invalid token',
                    'status' => 'failed'
                ], 400);

            *
            */

            // For live site

            return redirect("https://diyun.jmbliss.com/my/{$token}");
        }

        if (!$user->hasVerifiedEmail()) {
            if (!$user->verification_token) {
            /*
            *
            FOR LOCAL HOST
            *
                // Token has already been used
                return response([
                    'message' => 'Token has already been used',
                    'status' => 'failed'
                ], 400);

            *
            */

            // For live site

                return redirect("https://diyun.jmbliss.com/my/{$token}");
            }

            $user->markEmailAsVerified();
            $user->verification_token = null; // Invalidate the token
            $user->save();

            event(new Verified($user));

            // Redirect to your React application upon successful verification
            return redirect("https://diyun.jmbliss.com/my/{$token}");
        }

        return response([
            'message' => 'Email verified successfully',
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'email_verified_at' => $user->email_verified_at,
            'status' => 'success'
        ], 200);
    }
}
