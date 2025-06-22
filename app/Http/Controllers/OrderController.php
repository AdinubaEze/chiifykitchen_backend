<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource; 
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;    
use Illuminate\Validation\Rule;   

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index','show']); 
    } 

    public function index(Request $request)
    {
        try {
            $query = Order::with(['user', 'address', 'table', 'items.product']);
    
            // Handle user_id parameter if provided
            if ($request->has('user_id')) {
                $requestedUserId = $request->input('user_id');
                
                // Allow users to only view their own orders unless they're admin
                if ($request->user() && 
                    ($request->user()->id == $requestedUserId || 
                     $request->user()->role === User::ROLE_ADMIN)) {
                    $query->where('user_id', $requestedUserId);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You can only view your own orders'
                    ], 403);
                }
            }
            // If no user_id parameter but user is authenticated
            elseif ($request->user()) {
                // Admins see all orders when no user_id is specified
                if ($request->user()->role !== User::ROLE_ADMIN) {
                    $query->where('user_id', $request->user()->id);
                }
                // For admin, no additional where clause is added - they see all orders
            }
            // If no authentication and no user_id parameter
            else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required or user_id parameter needed'
                ], 401);
            }
    
            // Status filter
            if ($request->has('status')) {
                $status = $request->input('status');
                if (in_array($status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_PROCESSING,
                    Order::STATUS_COMPLETED,
                    Order::STATUS_CANCELLED,
                    Order::STATUS_DELIVERED,
                ])) {
                    $query->where('status', $status);
                }
            }
    
            // Search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('order_id', 'like', "%{$search}%")
                      ->orWhereHas('user', function($q) use ($search) {
                          $q->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%");
                      });
                });
            }
    
            // Pagination
            $perPage = min($request->per_page ?? 15, 100);
            $orders = $query->latest()->paginate($perPage);
    
            return response()->json([
                'status' => 'success',
                'data' => $orders,
            ]);
    
        } catch (\Exception $e) {
            Log::error('Order index failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve orders',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    } 

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_id' => ['nullable', 'exists:addresses,id,user_id,'.$request->user()->id],
            'table_id' => ['nullable', 'exists:tables,id'],
            'payment_method' => ['required', 'string'],
            'delivery_method' => ['required', Rule::in(Order::getDeliveryMethodOptions())],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    
        // Custom validation rules
        $validator->after(function ($validator) use ($request) {
            $deliveryMethod = $request->input('delivery_method');
            
            // Address validation for delivery/courier
            if (in_array($deliveryMethod, [Order::DELIVERY_METHOD_DELIVERY, Order::DELIVERY_METHOD_COURIER])) {
                if (!$request->input('address_id')) {
                    $validator->errors()->add('address_id', 'Address is required for delivery or courier orders.');
                }
            }
    
            // Table validation for dine-in
            if ($deliveryMethod === Order::DELIVERY_METHOD_DINE_IN && !$request->input('table_id')) {
                $validator->errors()->add('table_id', 'Table ID is required for dine-in orders.');
            }
    
            // Payment method validation - cash only allowed for dine-in or pickup
            if (!in_array($deliveryMethod, [Order::DELIVERY_METHOD_DINE_IN, Order::DELIVERY_METHOD_PICKUP]) && 
                $request->input('payment_method') === 'cash') {
                $validator->errors()->add('payment_method', 'Cash payment is only allowed for dine-in or pickup orders.');
            }
        });
    
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
            $products = Product::whereIn('id', collect($request->products)->pluck('id'))->get();
            
            $subtotal = 0;
            $items = [];
            
            foreach ($request->products as $item) {
                $product = $products->firstWhere('id', $item['id']);
                $price = $product->discounted_price ?? $product->price;
                $subtotal += $price * $item['quantity'];
                
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'discounted_price' => $product->discounted_price,
                ];
            }
    
            $deliveryFee = 0;
            if (in_array($request->delivery_method, [Order::DELIVERY_METHOD_DELIVERY, Order::DELIVERY_METHOD_COURIER])) {
                $settings = Setting::first();
                $deliveryFee = $settings->general_settings['delivery_fee'] ?? 0;
            }
            $total = $subtotal + $deliveryFee;
    
            $order = Order::create([
                'order_id' => 'ORD-' . Str::upper(Str::random(8)),
                'user_id' => $request->user()->id,
                'address_id' => $request->address_id,
                'table_id' => $request->table_id,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'delivery_method' => $request->delivery_method,
                'payment_status' => $request->payment_method === 'cash' 
                    ? Order::PAYMENT_STATUS_PENDING 
                    : Order::PAYMENT_STATUS_UNPAID,
                'status' => Order::STATUS_PENDING,
            ]);
    
            $order->items()->createMany($items);
    
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_id' => 'PAY-' . strtoupper(uniqid()),
                'amount' => $total,
                'payment_method' => $request->payment_method,
                'status' => $request->payment_method === 'cash' 
                ? Order::PAYMENT_STATUS_PENDING 
                : Order::PAYMENT_STATUS_UNPAID,
            ]);
    
            if ($request->table_id) {
                Table::where('id', $request->table_id)->update(['status' => Table::STATUS_OCCUPIED]);
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message'=>'The order has been created successfully.',
                'data' => new OrderResource($order->load(['user', 'address', 'table', 'items.product'])),
                'payment'=>$payment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Order creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    } 

    public function show($orderId)
    {
        try {
            // First check if order exists
            $order = Order::with(['user', 'address', 'table', 'items.product'])
                         ->find($orderId);
    
            if (!$order) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Order not found',
                    'error' => 'The requested order does not exist'
                ], 404);
            }
    
            // Then check authorization
            $this->authorize('view', $order);
    
            return response()->json([
                'status' => 'success',
                'data' => new OrderResource($order)
            ]);
    
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Unauthorized to view this order',
                'error' => 'You do not have permission to view this order'
            ], 403);
    
        } catch (\Exception $e) {
            Log::error('Order retrieval failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'user_id' => auth()->id(),
                'error' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    } 
 
    public function update(UpdateOrderRequest $request, Order $order)
    {
        DB::beginTransaction();
        
        try {
            $updates = $request->validated();
            
            // Track if admin is updating the order
            if ($request->user()->role === User::ROLE_ADMIN) {
                $updates['admin_id'] = $request->user()->id;
            }
    
            // Handle status changes
            if (isset($updates['status'])) {
                switch ($updates['status']) {
                    case Order::STATUS_COMPLETED:
                        if ($order->payment_method === Order::PAYMENT_METHOD_CASH) {
                            $updates['payment_status'] = Order::PAYMENT_STATUS_PAID;
                        }
                        break;
                        
                    case Order::STATUS_CANCELLED:
                        $updates['cancelled_by_customer'] = false;
                        $updates['customer_cancelled_at'] = null;
                        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
                            /* $updates['payment_status'] = Order::PAYMENT_STATUS_REFUNDED;
                            // Update related payment if exists
                            if ($payment = $order->payment) {
                                $payment->update(['status' => Payment::STATUS_REFUNDED]);
                            } */
                        }
                        break;
                        
                    case Order::STATUS_DELIVERED:
                        // Mark payment as successful if not already
                        if ($order->payment_status !== Order::PAYMENT_STATUS_PAID && $order->payment) {
                            $order->payment->update(['status' => Payment::STATUS_SUCCESSFUL]);
                            $updates['payment_status'] = Order::PAYMENT_STATUS_PAID;
                        }
                        break;
                }
            }
    
            $order->update($updates);
            if($order->payment){
                $order->payment->update(['status' => $updates['payment_status']]);
            }
    
            // If dine-in order is completed, mark table as available
            if ($order->status === Order::STATUS_COMPLETED && $order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => Table::STATUS_AVAILABLE]);
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Order updated successfully',
                'data' => new OrderResource($order->fresh()->load(['user', 'address', 'table', 'items.product'])),
                'updates'=>$updates,
            ]);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order update failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Order update failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    } 
 

    public function customerCancelOrder(Request $request, $orderId)
    {
        DB::beginTransaction();
    
        try {
            $order = Order::find($orderId);
            
            if (!$order) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Order not found'
                ], 404);
            }
    
            if ($order->user_id !== $request->user()->id) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'You can only cancel your own orders'
                ], 403);
            }
    
            if ($order->status !== Order::STATUS_PENDING) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Order can only be cancelled while in pending status'
                ], 400);
            }
    
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                // 'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
                'cancelled_by_customer' => true,
                'customer_cancelled_at' => now(),
            ]);
    
            if ($order->payment) {
                $order->payment->update(['status' => Payment::STATUS_REFUNDED]);
            }
    
            if ($order->table_id) {
                Table::where('id', $order->table_id)->update(['status' => Table::STATUS_AVAILABLE]);
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Order has been cancelled successfully',
                'data' => new OrderResource($order->fresh()->load(['user', 'address', 'table', 'items.product']))
            ]);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order cancellation failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'user_id' => $request->user()->id,
                'error' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel order',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
  
}