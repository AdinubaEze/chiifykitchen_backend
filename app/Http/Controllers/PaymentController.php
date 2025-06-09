<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        try {
            $query = Payment::with(['order', 'order.user','order.items','order.items.product'])
                ->latest();

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('payment_id', 'like', "%{$search}%")
                      ->orWhere('reference', 'like', "%{$search}%")
                      ->orWhereHas('order', function($q) use ($search) {
                          $q->where('order_id', 'like', "%{$search}%");
                      })
                      ->orWhereHas('order.user', function($q) use ($search) {
                          $q->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->has('method')) {
                $query->where('payment_method', $request->input('method'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('from') && $request->has('to')) {
                $query->whereBetween('created_at', [
                    $request->input('from'),
                    $request->input('to')
                ]);
            }

            $payments = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => $payments
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payments: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payments'
            ], 500);
        }
    }

    public function show(Payment $payment)
    {
        try {
            $payment->load([
                'order',
                'order.user',
                'order.items',
                'order.items.product',
                'order.address'
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment details: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payment details'
            ], 500);
        }
    }

    /* public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'status' => 'required|in:pending,successful,failed,refunded',
            'reference' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $payment->update([
                'status' => $request->status,
                'reference' => $request->reference ?? $payment->reference,
                'verified_at' => $request->status === 'successful' ? now() : null
            ]);

            // Update order payment status if payment status changes
            if ($payment->wasChanged('status')) {
                $payment->order->update([
                    'payment_status' => $this->mapPaymentStatusToOrderStatus($request->status)
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment updated successfully',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update payment: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment'
            ], 500);
        }
    } */
 
    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'status' => 'required|in:pending,successful,failed,refunded',
            'reference' => 'nullable|string|max:255',
        ]);
    
        DB::beginTransaction();
    
        try {
            $payment->update([
                'status' => $request->status,
                'reference' => $request->reference ?? $payment->reference,
                'verified_at' => $request->status === 'successful' ? now() : null
            ]);
    
            // Update order status based on payment status
            switch ($request->status) {
                case Payment::STATUS_SUCCESSFUL:
                    $payment->order->update([
                        'payment_status' => Order::PAYMENT_STATUS_PAID,
                        'status' => Order::STATUS_PROCESSING // Move to processing after payment
                    ]);
                    break;
                    
                case Payment::STATUS_FAILED:
                    $payment->order->update([
                        'payment_status' => Order::PAYMENT_STATUS_FAILED,
                        'status' => Order::STATUS_PENDING // Keep as pending for retry
                    ]);
                    break;
                    
                case Payment::STATUS_REFUNDED:
                    $payment->order->update([
                        'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
                        'status' => Order::STATUS_CANCELLED
                    ]);
                    break;
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Payment updated successfully',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update payment: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment'
            ], 500);
        }
    }

    protected function mapPaymentStatusToOrderStatus($paymentStatus)
    {
        return match($paymentStatus) {
            'successful' => 'paid',
            'failed' => 'failed',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

 

    public function initiatePayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:card,paystack,flutterwave,cash',
        ]);
    
        DB::beginTransaction();
    
        try {
            $order = Order::findOrFail($request->order_id);
            
            // Verify order amount matches payment amount
            if (abs($order->total - $request->amount) > 0.01) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment amount does not match order total'
                ], 422);
            }
    
            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_id' => 'PAY-' . strtoupper(uniqid()),
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'status' => 'pending',
            ]);
    
            // Handle different payment methods
            switch ($request->payment_method) {
                case 'card':
                case 'paystack':
                case 'flutterwave':
                    // Process online payment (implementation depends on your payment gateway)
                    $paymentResult = $this->processOnlinePayment($payment, $request);
                    break;
                    
                case 'cash':
                    // For cash payments, mark as pending
                    $payment->update(['status' => 'pending']);
                    $order->update(['payment_status' => 'pending']);
                    break;
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Payment initiated successfully',
                'data' => [
                    'payment' => $payment,
                    'payment_url' => $paymentResult['payment_url'] ?? null,
                ]
            ]);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment initiation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Payment initiation failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'reference' => 'required|string',
        ]);
    
        DB::beginTransaction();
    
        try {
            $payment = Payment::findOrFail($request->payment_id);
            
            // Verify payment with payment gateway
            $verificationResult = $this->verifyWithGateway($payment, $request->reference);
            
            if ($verificationResult['success']) {
                $payment->update([
                    'status' => 'successful',
                    'reference' => $request->reference,
                    'verified_at' => now(),
                    'metadata' => $verificationResult['metadata'] ?? null,
                ]);
    
                $payment->order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing', // Move order to processing after payment
                ]);
    
                DB::commit();
    
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment verified successfully',
                    'data' => $payment
                ]);
            }
    
            // If verification failed
            $payment->update([
                'status' => 'failed',
                'reference' => $request->reference,
                'metadata' => $verificationResult['metadata'] ?? null,
            ]);
    
            $payment->order->update([
                'payment_status' => 'failed',
            ]);
    
            DB::commit();
    
            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed',
                'data' => $payment
            ], 400);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
 

}