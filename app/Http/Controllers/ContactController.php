<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormMail;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function sendMail(Request $request)
    {
        try {
            // validator of inputs
            $data = $request->validate([
                'fullname' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:255',
                'attachments' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Process screenshot attachment
            $attachments = null;
            if ($request->hasFile('attachments')) {
                $attachments = $request->file('attachments');
            }

            // Send email through class app/mail/ContactFormMail
            
            /** 
             * 
             * CHANGE EMAIL ADDRESSS *
             * 
             * 
             * 
             **/

            Mail::to('jmbliss83@gmail.com')->send(new ContactFormMail(
                $data['fullname'],
                $data['email'],
                $data['subject'],
                $data['message'],
                $attachments
            ));

            // Pass the data to the view
            return response()->json(['message' => 'Your message has been sent to jmbliss83@gmail.com (change into contact controller)', 'data' => $data]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to send email. Please try again.'], 500);
        }
    }
}
