<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewUserCredentials;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    /**
     * Get paginated list of users with filters
     */
    public function index(Request $request)
    {
        try {
              
            $query = User::withCount('orders'); 
            // Apply filters
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }
    
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
    
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('firstname', 'like', "%$search%")
                      ->orWhere('lastname', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%");
                });
            }
            if ($request->has('min_orders')) {
                $query->having('orders_count', '>=', $request->min_orders);
            }
    
            $perPage = $request->per_page ?? 15;
            $users = $query->latest()->paginate($perPage);
    
            // Transform the users to include orders count in the desired format
            $transformedUsers = $users->getCollection()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar'=>$user->avatar,
                    'role' => $user->role,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'orders' => [
                        'count' => $user->orders_count,
                        // You can include more order-related data here if needed
                    ]
                ];
            });
    
            return response()->json([
                'message' => 'Users retrieved successfully',
                'status' => 'success',
                'data' => [
                    'data' => $transformedUsers,
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ]);
    
        } catch (\Exception $e) {
            Log::error('Failed to retrieve users', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to retrieve users',
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|between:2,100',
            'lastname' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'phone' => 'required|string|max:20',
            'role' => 'required|string|in:customer,admin,staff',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'status' => 'fail',
                'errors' => collect($validator->errors())->map(fn ($e) => $e[0])
            ], 400);
        }
    
        // Generate password before attempting to send email
        $password = Str::random(12);
        $email = $request->email;
    
        // 1. First verify we can send email
        try {
            $tempUser = new User([
                    'firstname' => $request->firstname,
                    'lastname' => $request->lastname,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'role' => $request->role,
                ]);
    
            // Test email delivery without creating user
             Mail::to($request->email)->send(new NewUserCredentials($tempUser, $password));
    
            // If we get here, email was sent successfully
        } catch (\Exception $emailException) {
            Log::error('Email send failed', [
                'email' => $email,
                'error' => $emailException->getMessage(),
                'trace' => $emailException->getTraceAsString()
            ]);
    
            return response()->json([
                'message' => 'Failed to send welcome email',
                'status' => 'fail',
                'error' => 'Email service unavailable',
                'details' => config('app.debug') ? $emailException->getMessage() : null
            ], 502); // 502 Bad Gateway for email failures
        }
    
        // 2. Now create user (only if email succeeded)
        try {
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $email,
                'phone' => $request->phone,
                'password' => Hash::make($password),
                'role' => $request->role,
                'email_verified_at' => now(),
            ]);
    
            // 3. Send confirmation email (second attempt as backup)
            try {
                Mail::to($email)->send(new NewUserCredentials($user, $password));
            } catch (\Exception $secondaryEmailException) {
                Log::error('Secondary email send failed', [
                    'user_id' => $user->id,
                    'error' => $secondaryEmailException->getMessage()
                ]);
                // Continue despite this error since we already validated email works
            }
    
            return response()->json([
                'message' => 'User created successfully',
                'status' => 'success',
                'data' => [
                    'user' => $user->only(['id', 'firstname', 'lastname', 'email', 'role']),
                    'password' => $password // Only shown once here
                ]
            ], 201);
    
        } catch (\Exception $dbException) {
            Log::error('User creation failed after email verification', [
                'email' => $email,
                'error' => $dbException->getMessage()
            ]);
    
            return response()->json([
                'message' => 'User creation failed after email verification',
                'status' => 'error',
                'error' => 'Database error',
                'details' => config('app.debug') ? $dbException->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get a specific user
     */
    public function show($id)
{
    try {
        $user = User::withCount('orders')->findOrFail($id);

        return response()->json([
            'message' => 'User retrieved successfully',
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'orders' => [
                        'count' => $user->orders_count,
                        // You can include more order details here if needed
                    ],
                    // Include other user fields as needed
                ]
            ]
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'User not found',
            'status' => 'fail'
        ], 404);
    } catch (\Exception $e) {
        Log::error('Failed to retrieve user', ['user_id' => $id, 'error' => $e->getMessage()]);
        return response()->json([
            'message' => 'Failed to retrieve user',
            'status' => 'error',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Update a user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'firstname' => 'sometimes|string|between:2,100',
                'lastname' => 'sometimes|string|between:2,100',
                'email' => 'sometimes|string|email|max:100|unique:users,email,'.$id,
                'phone' => 'sometimes|string|max:20',
                'role' => 'sometimes|string|in:customer,admin,staff',
                'status' => 'sometimes|string|in:active,suspended',
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

            // Update user fields
            $updateData = $request->only(['firstname', 'lastname', 'email', 'phone', 'role', 'status']);
            
            if (!empty($updateData)) {
                $user->update($updateData);
            }

            return response()->json([
                'message' => 'User updated successfully',
                'status' => 'success',
                'data' => [
                    'user' => $user->fresh()
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'status' => 'fail'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update user',
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // Prevent admin from deleting themselves
            if (auth()->id() === $user->id) {
                return response()->json([
                    'message' => 'You cannot delete your own account',
                    'status' => 'fail'
                ], 403);
            }

            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully',
                'status' => 'success'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'status' => 'fail'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to delete user',
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}