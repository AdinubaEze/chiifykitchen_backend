<?php
// app/Http/Controllers/Auth/PasswordResetController.php
namespace App\Http\Controllers\Auth;

use App\Auth\CustomPasswordBroker;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Log; 

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'status' => 'fail',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'If this email exists in our system, a reset link has been sent',
                    'status' => 'success'
                ]);
            }

            if ($user->google_id) {
                return response()->json([
                    'message' => 'This is a Google account. Please sign in with Google.',
                    'status' => 'fail',
                    'isGoogle'=> true,
                ], 400);
            }

            $response = Password::broker()->sendResetLink(
                $request->only('email')
            );

            return $response == Password::RESET_LINK_SENT
                ? response()->json([
                    'message' => 'Reset link sent to your email',
                    'status' => 'success'
                ])
                : response()->json([
                    'message' => trans($response),
                    'status' => 'fail',
                    'error' => trans($response),
                ], 500);

        } catch (\Exception $e) {
            Log::error('Password reset error', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'An error occurred while processing your request',
                'status' => 'error',
                'error'=>$e->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'status' => 'fail',
                'errors' => $validator->errors()
            ], 400);
        }

        $response = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $response == Password::PASSWORD_RESET
            ? response()->json([
                'message' => 'Password reset successfully',
                'status' => 'success'
            ])
            : response()->json([
                'message' => 'Failed to reset password',
                'status' => 'fail',
                'error' => trans($response)
            ], 400);
    }
}