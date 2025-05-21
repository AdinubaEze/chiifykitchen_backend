<?php
// app/Http/Controllers/Auth/VerificationController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VerificationController extends Controller
{
    /**
     * Verify the user's email address
     */
    // app/Http/Controllers/Auth/VerificationController.php
public function verify(Request $request)
{
    // Validate the request parameters
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer',
        'hash' => 'required|string',
    ]);

    // Return validation errors if any
    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $user = User::findOrFail($request->id);

        // Verify the hash matches
        if (!hash_equals((string) $request->hash, sha1($user->email))) {
            return response()->json([
                'message' => 'Invalid verification link',
                'status' => 'error'
            ], 403);
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
                'status' => 'success'
            ]);
        }

        // Mark as verified
        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email successfully verified',
            'status' => 'success',
            'verified' => true
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'User not found',
            'status' => 'error'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Verification failed',
            'status' => 'error',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Resend the verification email
     */
    // app/Http/Controllers/Auth/VerificationController.php
public function resend(Request $request)
{
    // Validate email input
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|max:255'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Find user by email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Return generic message to prevent email enumeration
            return response()->json([
                'message' => 'If this email exists in our system, a verification link has been sent',
                'status' => 'success'
            ]);
        }

        // Check if it's a Google account
        if ($user->google_id) {
            return response()->json([
                'message' => 'Google accounts are automatically verified',
                'status' => 'error'
            ], 400);
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified',
                'status' => 'success'
            ]);
        }

        // Resend verification notification
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link resent successfully',
            'status' => 'success'
        ]);

    } catch (\Exception $e) {
        Log::error('Email verification resend failed', [
            'email' => $request->email,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'message' => 'Failed to resend verification email',
            'status' => 'error',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
}