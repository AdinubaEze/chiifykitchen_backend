<?php
public function index(Request $request)
{
    try {
        $user = auth()->user();
        $isAdmin = $user && $user->role === User::ROLE_ADMIN;
        
        $query = Product::with([
            'category' => function($query) {
                $query->select('id', 'name', 'status', 'icon_image');
            },
            'productImages',
            'admin' => function($query) {
                $query->select('id', 'firstname', 'lastname', 'email');
            }
        ])
        ->when($isAdmin && $request->boolean('with_trashed'),
            fn($q) => $q->withTrashed(),
            fn($q) => $q->where('status', '!=', Product::STATUS_DELETED)
        );

        // Search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('category', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->has('status')) {
            $status = $request->input('status');
            $allowedStatuses = [Product::STATUS_ACTIVE, Product::STATUS_DISABLED];
            if ($isAdmin) {
                $allowedStatuses[] = Product::STATUS_DELETED;
            }
            
            if (in_array($status, $allowedStatuses)) {
                $query->where('status', $status);
            }
        }

        // Category filter
        if ($request->has('category_id')) {
            $categoryId = $request->input('category_id');
            $query->where('category_id', $categoryId);
        }

        // Featured filter
        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        // Price range filter
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $validSortFields = ['title', 'price', 'created_at', 'updated_at'];
        $sortField = in_array($sortField, $validSortFields) ? $sortField : 'created_at';
        
        $sortDirection = strtolower($request->input('sort_dir', 'desc'));
        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';
        
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = min(max($request->input('per_page', 15), 100), 100);
        $products = $query->paginate($perPage);

        // Transform the response to include category data
        $products->getCollection()->transform(function ($product) {
            return [
                'id' => $product->id,
                'title' => $product->title,
                'price' => $product->price,
                'discounted_price' => $product->discounted_price,
                'description' => $product->description,
                'status' => $product->status,
                'is_featured' => $product->is_featured,
                'image_url' => $product->image_url,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'status' => $product->category->status,
                    'icon_image_url' => $product->category->icon_image_url
                ] : null,
                'product_images' => $product->productImages->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url
                    ];
                }),
                'admin' => $product->admin ? [
                    'id' => $product->admin->id,
                    'name' => $product->admin->firstname . ' ' . $product->admin->lastname,
                    'email' => $product->admin->email
                ] : null
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $products,
            'is_admin' => $isAdmin
        ]);

    } catch (\Exception $e) {
        Log::error('Product index failed: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve products',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

public function show($id)
{
    try {
        $user = auth()->user();
        $isAdmin = $user && $user->role === User::ROLE_ADMIN;

        $product = Product::with([
            'category' => function($query) {
                $query->select('id', 'name', 'status', 'icon_image');
            },
            'productImages',
            'admin' => function($query) {
                $query->select('id', 'firstname', 'lastname', 'email');
            }
        ])
        ->when($isAdmin,
            fn($q) => $q->withTrashed(),
            fn($q) => $q->where('status', '!=', Product::STATUS_DELETED)
        )
        ->find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        // Transform the response
        $responseData = [
            'id' => $product->id,
            'title' => $product->title,
            'price' => $product->price,
            'discounted_price' => $product->discounted_price,
            'description' => $product->description,
            'status' => $product->status,
            'is_featured' => $product->is_featured,
            'image_url' => $product->image_url,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'status' => $product->category->status,
                'icon_image_url' => $product->category->icon_image_url
            ] : null,
            'product_images' => $product->productImages->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url
                ];
            }),
            'admin' => $product->admin ? [
                'id' => $product->admin->id,
                'name' => $product->admin->firstname . ' ' . $product->admin->lastname,
                'email' => $product->admin->email
            ] : null
        ];

        return response()->json([
            'status' => 'success',
            'data' => $responseData
        ]);

    } catch (\Exception $e) {
        Log::error('Product show failed: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve product',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}