<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Google_Client;
use Illuminate\Support\Facades\Http;

class GoogleAuthController extends Controller
{
    public function googleSign(Request $request)
    {
        // Validate the request has either credential or access_token
        $request->validate([
            'credential' => 'required_without:access_token|string',
            'access_token' => 'required_without:credential|string'
        ]);

        // Handle both credential (Google Sign-In) and access_token (OAuth) flows
        if ($request->has('credential')) {
            $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
            $payload = $client->verifyIdToken($request->credential);
            
            if (!$payload) { 
                return response()->json([
                    'message' => 'Invalid Google ID token',
                    'error' => 'Invalid Google ID token',
                    'status' => 'fail'
                ], 401);
            }
        } else {
            $payload = Http::get("https://www.googleapis.com/oauth2/v3/userinfo", [
                'access_token' => $request->access_token,
            ])->json();

            if (!isset($payload['email'])) {
                return response()->json([
                    'message' => 'Invalid Google access token',
                    'error' => 'Invalid Google access token',
                    'status' => 'fail'
                ], 401);
            }
        }

        try {
            // Check if user exists first
            $user = User::where('email', $payload['email'])->first();
            $message = "Account created successfully";

            if ($user) {
                if ($user->status === User::STATUS_SUSPENDED) {
                   return response()->json([
                       'message' => 'Your account has been suspended. Please contact support.',
                       'status' => 'fail',
                       'suspended' => true
                   ], 403);
                }

                $message = "Your have successfully signed in";
                // User exists - update without changing avatar
                $user->update([
                    'google_id' => $payload['sub'] ?? $user->google_id,
                    'firstname' => $payload['given_name'] ?? $user->firstname,
                    'lastname' => $payload['family_name'] ?? $user->lastname,
                ]);
            } else {
                // New user - create with avatar
                $user = User::create([
                    'google_id' => $payload['sub'] ?? null,
                    'role'=>User::ROLE_CUSTOMER,
                    'firstname' => $payload['given_name'] ?? 'Google',
                    'lastname' => $payload['family_name'] ?? 'User',
                    'email' => $payload['email'],
                    'avatar' => $payload['picture'] ?? null,
                    'password' => bcrypt(Str::random(24)),
                    'email_verified_at' => now(),
                ]);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message'=>$message,
                'access_token' => "Bearer $token",
                'token_type' => 'bearer',
                'status' => "success",
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'avatar' => $user->avatar, // Will be null for existing users unless they had one
                    'role' =>$user->role,
                    'is_google_user' => true, 
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Google authentication failed', [
                'message' => $e->getMessage(),
                'status' => 'error',
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            
            return response()->json([
                'status' => 'error',
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}