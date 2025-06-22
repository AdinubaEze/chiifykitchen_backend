<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index','show','getRelatedProducts','getProductsByIds']);
        $this->middleware('role:admin')->except(['index', 'show','getRelatedProducts','getProductsByIds']);
    }
 
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $isAdmin = $user && $user->role === User::ROLE_ADMIN;
            $query = Product::with(['category', 'productImages','admin'])
            ->when($isAdmin && $request->boolean('with_trashed'),
                fn($q) => $q->withTrashed(),
                fn($q) => $q->where('status', '!=', Product::STATUS_DELETED)
            ); 

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $status = $request->input('status');
                if (in_array($status, [Product::STATUS_ACTIVE, Product::STATUS_DISABLED,Product::STATUS_DELETED])) {
                    $query->where('status', $status);
                }
            }

            if ($request->has('category_id')) {
                $categoryId = $request->input('category_id');
                $query->where('category_id', $categoryId);
            }

            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->boolean('is_featured'));
            }

            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_dir', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $perPage = $request->input('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $products, 
            ]);

        } catch (\Exception $e) {
            Log::error('Product index failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve products because '.$e->getMessage(), 
            ], 500);
        }
    }

    public function getProductsByIds(Request $request)
{
    try { 
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:products,id'
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

        $productIds = $request->input('ids');
        $products = Product::with(['category', 'productImages'])
            ->whereIn('id', $productIds)
            ->where('status', '!=', Product::STATUS_DELETED)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);

    } catch (\Exception $e) {
        Log::error('Failed to get products by IDs: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve products'
        ], 500);
    }
}
 
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'status' => 'sometimes|in:1,2',
            'is_featured' => 'sometimes|boolean'
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

        try {
            $data = $validator->validated();
            $data['admin_id'] = auth()->id();
            $data['status'] = $data['status'] ?? Product::STATUS_ACTIVE;

            $product = Product::create($data);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product->load(['category', 'productImages'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with(['category', 'productImages'])
                ->where('status', '!=', Product::STATUS_DELETED)
                ->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            Log::error('Product show failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product'
            ], 500);
        }
    }

 
    public function getRelatedProducts($id)
    {
        try {
            $product = Product::find($id);
            
            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found'
                ], 404);
            }
    
            $relatedProducts = Product::where('id', '!=', $id)
                ->where(function($query) use ($product) {
                    $query->where('category_id', $product->category_id)
                        ->orWhere('title', 'like', '%'.$product->title.'%')
                        ->orWhere('description', 'like', '%'.$product->description.'%');
                })
                ->where('status', Product::STATUS_ACTIVE)
                ->with(['category', 'productImages'])
                ->limit(5) // You can adjust this number
                ->get();
    
            return response()->json([
                'status' => 'success',
                'data' => $relatedProducts
            ]);
    
        } catch (\Exception $e) {
            Log::error('Failed to get related products: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve related products'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|exists:categories,id',
            'status' => 'sometimes|in:1,2',
            'is_featured' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $product->update($validator->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product->fresh()->load(['category', 'productImages'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        try {
            $product->status = Product::STATUS_DELETED;
            $product->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Product deletion failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product'
            ], 500);
        }
    }

    public function uploadMainImage(Request $request, $id)
    {
        $product = Product::find($id);
    
        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }
    
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
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
    
        try {
            // Delete old image if exists
            if ($product->image) {
                $oldImagePath = str_replace('storage/', '', $product->image);
                if (Storage::disk('public')->exists($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            }
    
            // Store new image
            $path = $request->file('image')->store('products/main', 'public');
            $product->image = 'storage/' . $path;
            $product->save();
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Main image uploaded successfully',
                'data' => [
                    'image_url' => $product->image_url
                ]
            ]);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Main image upload failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload main image',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function uploadAdditionalImages(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
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
        try {
            $uploadedImages = [];

            foreach ($request->file('images') as $image) {
                $path = $image->store('products/additional', 'public');
                $productImage = ProductImage::create([
                    'product_id' => $product->id,
                    'path' => 'storage/' . $path
                ]);
                $uploadedImages[] = $productImage;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Additional images uploaded successfully',
                'data' => $uploadedImages, 
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Additional images upload failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload additional images because '. $e->getMessage()
            ], 500);
        }
    }

    public function deleteAdditionalImage($productId, $imageId)
    {
        $productImage = ProductImage::where('product_id', $productId)
            ->find($imageId);

        if (!$productImage) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image not found'
            ], 404);
        }

        try {
            Storage::delete(str_replace('storage/', 'public/', $productImage->path));
            $productImage->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Image deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Image deletion failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete image'
            ], 500);
        }
    }
}