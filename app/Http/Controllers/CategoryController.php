<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->only(['store', 'update', 'destroy','uploadIconImage','deleteIconImage']);
        $this->middleware('role:admin')->only(['store', 'update', 'destroy','uploadIconImage','deleteIconImage']);
    }

    public function index(Request $request)
    {
        try {
            $query = Category::with('admin');

            if ($request->has('search')) {
                try {
                    $search = $request->input('search');
                    if (!is_string($search)) {
                        throw new \InvalidArgumentException('Search parameter must be a string');
                    }

                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                } catch (\Exception $e) {
                    Log::error('Search failed', [
                        'error' => $e->getMessage(),
                        'search' => $request->input('search')
                    ]);
                }
            }

            if ($request->has('status')) {
                try {
                    $status = $request->input('status');
                    if (!in_array($status, [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE])) {
                        throw new \InvalidArgumentException('Invalid status value');
                    }
                    $query->where('status', $status);
                } catch (\Exception $e) {
                    Log::error('Status filter failed', [
                        'error' => $e->getMessage(),
                        'status' => $request->input('status')
                    ]);
                }
            }

            try {
                $sortField = $request->input('sort_by', 'created_at');
                $sortDirection = $request->input('sort_dir', 'desc');

                $validSortFields = ['name', 'status', 'created_at', 'updated_at'];
                if (!in_array($sortField, $validSortFields)) {
                    $sortField = 'created_at';
                }

                if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                    $sortDirection = 'desc';
                }

                $query->orderBy($sortField, $sortDirection);
            } catch (\Exception $e) {
                Log::error('Sorting failed', [
                    'error' => $e->getMessage(),
                    'sort_by' => $request->input('sort_by'),
                    'sort_dir' => $request->input('sort_dir')
                ]);
                $query->orderBy('created_at', 'desc');
            }

            try {
                $perPage = $request->input('per_page', 15);
                $perPage = min(max((int)$perPage, 1), 100);
                $categories = $query->paginate($perPage);
            } catch (\Exception $e) {
                Log::error('Pagination failed', [
                    'error' => $e->getMessage(),
                    'per_page' => $request->input('per_page')
                ]);
                $categories = $query->paginate(15);
            }

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);

        } catch (\Throwable $e) {
            Log::critical('Category index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to retrieve categories',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:categories,name',
                'description' => 'nullable|string|max:1000',
                'status' => 'required|in:0,1', 
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'meta_keywords' => 'nullable|string|max:255',
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
            DB::beginTransaction(); 
            $data = $validator->validated();
            $data['admin_id'] = auth()->id(); 
            $category = Category::create($data); 
            DB::commit(); 
            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);

        } catch (TokenExpiredException|TokenInvalidException|JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e instanceof TokenExpiredException ? 'Token expired' :
                            ($e instanceof TokenInvalidException ? 'Token invalid' : 'Token absent or invalid'),
                'error' => strtolower(class_basename($e))
            ], 401);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Category creation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    } 

    public function show($id)
    {
        try {
            $category = Category::with('admin')->find($id);
    
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }
    
            if ($category->status == Category::STATUS_INACTIVE && !auth()->check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not available'
                ], 404);
            } 
            
            // The icon_image_url will be automatically included because of the $appends property
            return response()->json([
                'status' => 'success',
                'data' => $category
            ]);
    
        } catch (\Exception $e) {
            Log::error('Category show failed', [
                'error' => $e->getMessage(),
                'category_id' => $id
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve category',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $category->id,
                'description' => 'nullable|string|max:1000',
                'status' => 'sometimes|required|in:0,1', 
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'meta_keywords' => 'nullable|string|max:255',
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
            $data = $validator->validated();
            $category->update($data);
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully',
                'data' => $category
            ]);
        } catch (TokenExpiredException|TokenInvalidException|JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e instanceof TokenExpiredException ? 'Token expired' :
                            ($e instanceof TokenInvalidException ? 'Token invalid' : 'Token absent or invalid'),
                'error' => strtolower(class_basename($e))
            ], 401);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Category update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
                'category_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid category ID'
                ], 400);
            }
            DB::beginTransaction();
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }

            if (method_exists($category, 'products') && $category->products()->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete category with associated products'
                ], 422);
            }
            if ($category->icon_image) {
                try {
                    if (Storage::disk('public')->exists($category->icon_image)) {
                        Storage::disk('public')->delete($category->icon_image);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to delete category icon', [
                        'error' => $e->getMessage(),
                        'category_id' => $id,
                        'icon_path' => $category->icon_image
                    ]);

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to delete category icon',
                        'error' => config('app.debug') ? $e->getMessage() : null
                    ], 500);
                }
            }

            if (!$category->delete()) {
                throw new \Exception('Failed to delete category from database');
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully'
            ]);

        } catch (TokenExpiredException|TokenInvalidException|JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e instanceof TokenExpiredException ? 'Token expired' :
                            ($e instanceof TokenInvalidException ? 'Token invalid' : 'Token absent or invalid'),
                'error' => strtolower(class_basename($e))
            ], 401);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Category deletion failed: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'category_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete category',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    public function uploadIconImage(Request $request, $id)
    {
        try {
            $category = Category::findOrFail($id);
    
            $validator = Validator::make($request->all(), [
                'icon_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            if ($category->icon_image) {
                // Remove 'storage/' prefix if it exists before deleting
                $pathToDelete = str_replace('storage/', '', $category->icon_image);
                Storage::disk('public')->delete($pathToDelete);
            }
    
            $path = $request->file('icon_image')->store('category_icons', 'public');
            
            // Store the path with 'storage/' prefix
            $storagePath = 'storage/' . $path;
            $category->update(['icon_image' => $storagePath]);
    
            // Add full URL to the response 
    
            return response()->json([
                'status' => 'success',
                'message' => 'Icon image uploaded successfully',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    } 
    public function deleteIconImage($id)
    {
        try {
            $category = Category::findOrFail($id);
    
            if (!$category->icon_image) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No icon image to delete'
                ], 404);
            }
    
            // Remove 'storage/' prefix before deleting
            $pathToDelete = str_replace('storage/', '', $category->icon_image);
            Storage::disk('public')->delete($pathToDelete);
            
            $category->update(['icon_image' => null]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Icon image deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deletion failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

}
