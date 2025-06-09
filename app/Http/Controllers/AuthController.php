<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\CustomVerifyEmailNotification; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; 
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
 

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function register(Request $request)
    {
        // Validation with formatted error responses
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|between:2,100',
            'lastname' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|confirmed|min:6',
            'role' => 'required|string|in:customer,admin,staff',
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $formattedErrors = [];
            foreach ($errors as $field => $messages) {
                $formattedErrors[$field] = $messages[0];
            }
        
            return response()->json([
                'message' => 'Validation failed',
                'status' => 'fail',
                'errors' => $formattedErrors
            ], 400);
        }
    
        // Start database transaction
        DB::beginTransaction();
    
        try {
            // Create user with default role 'customer' if not provided
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? User::ROLE_CUSTOMER, // Set default role
            ]);
    
            // Attempt to send verification email
            try {
                
                // Send custom verification notification
              $user->notify(new CustomVerifyEmailNotification);
                // If email sent successfully, commit transaction
                DB::commit();
    
                $token = JWTAuth::fromUser($user);
    
                return response()->json([
                    'message' => 'User registered successfully. Please check your email to verify your account.',
                    'status' => 'success',
                    'user' => $user,
                    'token' => "Bearer $token",
                    'email_verified' => false,
                ], 201);
    
            } catch (\Exception $emailException) {
                // Email failed to send - roll back user creation
                DB::rollBack();
                
                Log::error('Email sending failed', [
                    'error' => $emailException->getMessage(),
                    'email' => $request->email
                ]);
    
                return response()->json([
                    'message' => 'Registration failed - could not send verification email',
                    'status' => 'fail',
                    'error' => $emailException->getMessage(),
                    'email_error' => true
                ], 500);
            }
    
        } catch (\Exception $e) {
            // General registration error
            DB::rollBack();
            
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
    
            return response()->json([
                'message' => "Registration failed because ".$e->getMessage(),
                'status' => 'fail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
    
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
               // Check if the email exists but has a google_id
               $user = User::where('email', $credentials['email'])->first();
               if(!$user){
                   return response()->json([
                       'message' => 'No account found with this email. Please sign up to create a new account.',
                       'status' => 'fail',
                       'error' => 'Account not found'
                   ], 401);
               }
               if ($user && !is_null($user->google_id)) {
                   return response()->json([
                       'message' => 'This account was registered with Google. Please sign in using Google.',
                       'status' => 'fail',
                       'error' => 'Google sign-in required'
                   ], 401);
                }
                return response()->json([
                    'message' => 'Invalid credentials',
                    'status' => 'fail',
                    'error' => 'Invalid credentials'
                ], 401);
            }
    
            // Get the authenticated user
            $user = auth()->user();
      
            // Check if non-Google user has verified email
            if (is_null($user->google_id)) {
                if (!$request->user()->hasVerifiedEmail()) {
                    // Resend verification email
                    try {
                        $request->user()->sendEmailVerificationNotification();
                        
                        return response()->json([
                            'message' => 'Verification email resent. Please check your email to verify your account.',
                            'status' => 'fail',
                            'error' => 'Email not verified',
                            'requires_verification' => true,
                            'email_resent' => true
                        ], 403);
                    } catch (\Exception $emailException) {
                        Log::error('Failed to resend verification email', [
                            'user_id' => $user->id,
                            'error' => $emailException->getMessage()
                        ]);
                        
                        return response()->json([
                            'message' => 'Email verification required but failed to resend verification email',
                            'status' => 'error',
                            'error' => $emailException->getMessage(),
                            'requires_verification' => true,
                            'email_resent' => false
                        ], 500);
                    }
                }
            }
    
            return $this->respondWithToken($token);
    
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Could not create token',
                'status' => 'error',
                'error' => 'Could not create token'
            ], 500);
        }
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out','status'=>'success'],200);
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (JWTException $e) {
            return response()->json(["status"=>"fail",'error' => 'Could not refresh token'], 500);
        }

        return $this->respondWithToken($newToken,"User refreshed successfully");
    }
  
    public function updateProfile(Request $request)
    {
        $user = auth()->user();
    
        $validator = Validator::make($request->all(), [
            'firstname' => 'sometimes|string|max:100',
            'lastname' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:20',
        ]);
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
    
        try {
            // Get the authenticated user instance properly
            $user = User::find(auth()->id());
            
            // Update fields if they exist in request
            if ($request->has('firstname')) {
                $user->firstname = $request->firstname;
            }
            if ($request->has('lastname')) {
                $user->lastname = $request->lastname;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
    
            // Save the changes
            $user->save();
    
            return response()->json([
                'message' => 'Profile updated successfully',
                'status'=>'success',
                'user' => $this->getUserWithFormattedAvatar($user->fresh())
            ]);
    
        } catch (\Exception $e) {
            Log::error('Profile update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Profile update failed',
                'status'=>'fail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me()
    {
        $user = auth()->user();

        return response()->json([
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'role' => $user->role,
        ]);
    }
    public function uploadAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
    
        $user = $request->user();
    
        try {
            // Store new avatar first (in case deletion fails, we still have the old one)
            $filename = 'avatar_'.$user->id.'_'.time().'.'.$request->file('avatar')->extension();
            $path = $request->file('avatar')->storeAs('avatars', $filename, 'public');
            $avatarPath = '/storage/avatars/' . $filename;
    
            // Delete old avatar if exists (after successful upload)
            if ($user->avatar) {
                try {
                    $this->deleteAvatarFile($user->avatar);
                } catch (\Exception $e) {
                    Log::error('Failed to delete old avatar', [
                        'user_id' => $user->id,
                        'avatar_path' => $user->avatar,
                        'error' => $e->getMessage()
                    ]);
                    // Continue anyway since we already have the new avatar
                }
            }
    
            // Update user record
            $user->update(['avatar' => $avatarPath]);
    
            return response()->json([
                'message' => 'Avatar successfully uploaded',
                'status' => 'success',
                'avatar_url' => $this->getUserWithFormattedAvatar($user->fresh())->avatar,
                'user' => $this->getUserWithFormattedAvatar($user->fresh())
            ]);
    
        } catch (\Exception $e) {
            // Clean up if the new avatar was stored but other operations failed
            if (isset($path)) {
                try {
                    Storage::disk('public')->delete('avatars/'.$filename);
                } catch (\Exception $cleanupException) {
                    Log::error('Failed to clean up new avatar after upload error', [
                        'user_id' => $user->id,
                        'error' => $cleanupException->getMessage()
                    ]);
                }
            }
    
            return response()->json([
                'message' => 'Avatar upload failed',
                'status' => 'fail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function getUserWithFormattedAvatar($user)
    {
        if (!$user->avatar) {
            return $user;
        }
        
        // If this is a Google user and avatar is already a full URL, return as-is
        if ($user->google_id && filter_var($user->avatar, FILTER_VALIDATE_URL)) {
            return $user;
        }
        
        // For non-Google users, ensure the avatar has the full URL if it's a local path
        if (!$user->google_id && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
            // If the path doesn't start with http(s), prepend the app URL
            if (!preg_match('/^https?:\/\//', $user->avatar)) {
                $user->avatar = url($user->avatar);
            }
        }
        
        return $user;
    }
      
    /**
     * Remove the user's avatar
     */
    public function removeAvatar(Request $request)
    {
        $user = $request->user();
    
        try {
            if (!$user->avatar) {
                return response()->json([
                    'message' => 'No avatar to remove',
                    'status' => 'fail'
                ], 404);
            }
    
            // Delete the avatar file from storage
            $this->deleteAvatarFile($user->avatar);
    
            // Update user record - set avatar to null
            $user->update(['avatar' => null]);
    
            return response()->json([
                'message' => 'Avatar successfully removed',
                'status' => 'success',
                'user' => $this->getUserWithFormattedAvatar($user->fresh())
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Avatar removal failed',
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
    * Helper method to delete avatar file from storage
    */
    protected function deleteAvatarFile($avatarUrl)
    {
        try {
            // Handle both full URLs and storage paths
            $path = str_replace(url('/storage'), 'public', $avatarUrl);
            $path = str_replace('/storage', 'public', $path);
            
            if (Storage::exists($path)) {
                Storage::delete($path);
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete avatar file: ".$e->getMessage());
        }
    } 

    
    protected function respondWithToken($token,$message = "Login successful")
    {
        $user = auth()->user(); 
        $this->getUserWithFormattedAvatar($user);
        return response()->json([
            'message'=>$message,
            'status'=>"success",
            'access_token' => "Bearer $token",
            'token_type' => 'Bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'role' => $user->role,  
            ]
        ]);
    }
   
}