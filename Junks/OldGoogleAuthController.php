<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth; 
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Google_Client;

class GoogleAuthController extends Controller
{
    public function googleSign(Request $request)
    {
        // $accessToken = $request->input('access_token');

        // $googleUser = Http::get("https://www.googleapis.com/oauth2/v3/userinfo", [
        //     'access_token' => $accessToken,
        // ])->json(); 
 
        // if (!$googleUser || !isset($googleUser['email'])) {
        //     return response()->json(['error' => 'Invalid Google token'], 401);
        // }

        $credential = $request->input('credential'); 
        $accessToken = $request->input('access_token');
        if($credential){
            $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
    
            $payload = $client->verifyIdToken($credential);
    
            if (!$payload) {
                return response()->json(['error' => 'Invalid token'], 401);
            }
        }elseif($accessToken){
              $payload = Http::get("https://www.googleapis.com/oauth2/v3/userinfo", [
                  'access_token' => $accessToken,
              ])->json(); 
       
              if (!$payload || !isset($payload['email'])) {
                  return response()->json(['error' => 'Invalid Google token'], 401);
              }
        }



        try {
            $user = User::updateOrCreate(
                ['google_id' => $payload['sub']],
                [ 
                    'email' => $payload['email'],
                    'google_id' => $payload['sub'],
                    'avatar' => $payload['picture'] ?? null,
                    'firstname' => $payload['given_name'] ?? null,
                    'lastname' => $payload['family_name'] ?? null,
                    'password' => bcrypt(Str::random(16)),
                ]
            ); 
        } catch (\Exception $e) {
            Log::error('User updateOrCreate failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create or update user', 'message' => $e->getMessage()], 500);
        }

        $token = JWTAuth::fromUser($user); 
        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
}