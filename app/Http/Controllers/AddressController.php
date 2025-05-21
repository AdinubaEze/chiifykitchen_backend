<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; 

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Get all addresses for the authenticated user
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $addresses
        ]);
    }

    // Add a new address
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'is_default' => 'sometimes|boolean'
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $formattedErrors = [];
            foreach ($errors as $field => $messages) {
                $formattedErrors[$field] = $messages[0];
            }
        
            return response()->json([
                'status' => 'fail',
                'message' => 'Validation failed',
                'errors' => $formattedErrors
            ], 400);
        }
    
        $data = $validator->validated();
    
        // If setting as default, unset any existing default
        if (isset($data['is_default']) && $data['is_default']) {
            $request->user()->addresses()->where('is_default', true)->update(['is_default' => false]);
        }
    
        $address = $request->user()->addresses()->create($data);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Address added successfully',
            'data' => $address
        ], 201);
    }

    // Update an existing address
   public function update(Request $request, $id)
    {
        try {
            $address = $request->user()->addresses()->find($id);
        
            if (!$address) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Address not found'
                ], 404);
            }
        
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'street' => 'sometimes|string|max:255',
                'city' => 'sometimes|string|max:255',
                'state' => 'sometimes|string|max:255',
                'is_default' => 'sometimes|boolean'
            ], [
                // Custom error messages (optional)
                'name.max' => 'The name should not exceed 255 characters',
                'phone.max' => 'Phone number is too long',
            ]);
        
            if ($validator->fails()) {
                $errors = $validator->errors()->toArray();
                $formattedErrors = collect($errors)->mapWithKeys(function ($messages, $field) {
                    return [$field => $messages[0]]; // Return first error for each field
                })->all();
            
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Validation failed',
                    'errors' => $formattedErrors
                ], 400);
            }
        
            $data = $validator->validated();
        
            // Start database transaction for atomic updates
            DB::beginTransaction();
        
            try {
                // If setting as default, unset any existing default
                if (!empty($data['is_default']) && $data['is_default']) {
                    $request->user()->addresses()
                        ->where('id', '!=', $id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }
        
                $address->update($data);
        
                DB::commit();
        
                return response()->json([
                    'status' => 'success',
                    'message' => 'Address updated successfully',
                    'data' => $address->fresh() // Return fresh instance from database
                ]);
        
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Log the error for debugging
                Log::error('Address update failed: ' . $e->getMessage(), [
                    'address_id' => $id,
                    'user_id' => $request->user()->id,
                    'data' => $request->all()
                ]);
        
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update address',
                    'error' => 'An unexpected error occurred'
                ], 500);
            }
        
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete an address
    public function destroy(Request $request, $id)
    {
        $address = $request->user()->addresses()->find($id);

        if (!$address) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Address not found'
            ], 404);
        }

        $address->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted successfully'
        ]);
    }

    // Set an address as default
    public function setDefault(Request $request, $id)
    {
        $address = $request->user()->addresses()->find($id);

        if (!$address) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Address not found'
            ], 404);
        }

        // Unset any existing default
        $request->user()->addresses()
            ->where('id', '!=', $id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set this address as default
        $address->update(['is_default' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Default address set successfully',
            'data' => $address
        ]);
    }
}