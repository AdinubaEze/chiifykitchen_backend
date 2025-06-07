<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TableController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index','show']);
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        try {
            $query = Table::query();

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $status = $request->input('status');
                if (in_array($status, [
                    Table::STATUS_AVAILABLE,
                    Table::STATUS_OCCUPIED,
                    Table::STATUS_RESERVED,
                    Table::STATUS_MAINTENANCE
                ])) {
                    $query->where('status', $status);
                }
            }

            if ($request->has('min_capacity')) {
                $query->where('capacity', '>=', $request->input('min_capacity'));
            }

            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $perPage = $request->input('per_page', 15);
            $tables = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $tables,
            ]);

        } catch (\Exception $e) {
            Log::error('Table index failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve tables',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:tables',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'status' => 'required|in:available,occupied,reserved,maintenance',
            'location' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[$field] = $messages[0];
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $errors
            ], 422);
        }

        try {
            $table = Table::create($validator->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Table created successfully',
                'data' => $table
            ], 201);

        } catch (\Exception $e) {
            Log::error('Table creation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create table'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $table = Table::find($id);

            if (!$table) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Table not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $table
            ]);

        } catch (\Exception $e) {
            Log::error('Table show failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve table'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $table = Table::find($id);

        if (!$table) {
            return response()->json([
                'status' => 'error',
                'message' => 'Table not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:tables,name,'.$id,
            'description' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:available,occupied,reserved,maintenance',
            'location' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[$field] = $messages[0];
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $errors
            ], 422);
        }

        try {
            $table->update($validator->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Table updated successfully',
                'data' => $table
            ]);

        } catch (\Exception $e) {
            Log::error('Table update failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update table'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $table = Table::withTrashed()->find($id); // Include soft-deleted records
    
        if (!$table) {
            return response()->json([
                'status' => 'error',
                'message' => 'Table not found'
            ], 404);
        }
    
        try {
            $table->forceDelete(); // Permanently deletes NOW
    
            return response()->json([
                'status' => 'success',
                'message' => 'Table permanently deleted'
            ]);
        } catch (\Exception $e) {
            Log::error('Table permanent deletion failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to permanently delete table'
            ], 500);
        }
    }
}