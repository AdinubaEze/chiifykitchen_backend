<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function authenticateWithGoogle(Request $request)
    {
        $token = $request->input('token');
        
        $client = new GoogleClient(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($token);
        
        if (!$payload) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        $user = User::where('email', $payload['email'])->first();
        
        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => bcrypt(Str::random(16)), // Random password since we're using Google auth
                'google_id' => $payload['sub'],
                'email_verified_at' => now(), // Mark as verified since Google verified it
            ]);
        }
        
        // Log the user in
        Auth::login($user);
        
        // Create API token (if using API auth)
        $token = $user->createToken('google-auth-token')->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }
    
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        
        return response()->json(['message' => 'Logged out successfully']);
    }

     /* public function uploadAvatar(Request $request)
     {
         $validator = Validator::make($request->all(), [
             'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
         ]);
     
         if ($validator->fails()) {
             return response()->json($validator->errors(), 400);
         }
     
         $user = $request->user(); // Using request->user() instead of auth()->user()
     
         // Delete old avatar if exists
         if ($user->avatar) {
             $oldAvatar = str_replace('/storage', 'public', $user->avatar);
             Storage::delete($oldAvatar);
         }
     
         // Store new avatar
         $path = $request->file('avatar')->store('avatars', 'public');
         $avatarUrl = Storage::url($path);
         
         // Update and save user
         $user->update(['avatar' => $avatarUrl]);
     
           
         return response()->json([
             'message' => 'Avatar successfully uploaded', 
             'status'=>'success',
             'user' => $this->getUserWithFormattedAvatar($user->fresh()) // Return fresh instance
         ]);
     } */



     

    /*  public function removeAvatar(Request $request)
     {
         $user = $request->user();
     
         if ($user->avatar) {
             $oldAvatar = str_replace('/storage', 'public', $user->avatar);
             Storage::delete($oldAvatar);
             
             // Update and save user
             $user->update(['avatar' => null]);
     
             return response()->json([
                 'message' => 'Avatar successfully removed',
                 'status'=>'success',
                 'user' => $user->fresh()
             ]);
         }
     
         return response()->json([
             'message' => 'No avatar to remove'
         ], 404);
     } */
} 
 

