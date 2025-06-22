<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin')->except(['index', 'show','verifyPayment']);
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

    
    private function verifyPaystackPayment($reference, $orderId)
    {
        try {
            // 1. Get settings and payment gateway config
            $settings = Setting::first();
            if (!$settings) {
                throw new \Exception('Settings not found');
            }
    
            $paystackConfig = collect($settings->payment_gateways)
                ->firstWhere('id', 'paystack');
    
            if (!$paystackConfig) {
                throw new \Exception('Paystack configuration not found');
            }
    
            // 2. Check if Paystack is enabled
            if (empty($paystackConfig['enabled'])) {
                throw new \Exception('Paystack payment gateway is disabled');
            }
    
            // 3. Get the correct secret key based on transaction mode
            $secretKey = $settings->transaction_mode === 'test'
                ? $paystackConfig['secret_test_key']
                : $paystackConfig['secret_key'];
    
            if (empty($secretKey)) {
                $mode = $settings->transaction_mode === 'test' ? 'test' : 'live';
                throw new \Exception("Paystack {$mode} secret key is not configured");
            }
    
            // 4. Verify the transaction
            $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
    
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$secretKey}",
                    'Content-Type' => 'application/json',
                ],
                'http_errors' => false // Don't throw exceptions for HTTP errors
            ]);
    
            // 5. Handle response
            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody(), true);
    
            if ($statusCode !== 200) {
                throw new \Exception($responseBody['message'] ?? 'Paystack API request failed');
            }
    
            // 6. Validate the transaction
            $order = Order::findOrFail($orderId);
    
            if ($responseBody['status'] !== true || 
                $responseBody['data']['status'] !== 'success') {
                throw new \Exception('Transaction not successful');
            }
    
            // Convert amount to kobo (Paystack uses kobo)
            $amountPaid = $responseBody['data']['amount'];
            $orderAmount = $order->total * 100;
    
            if ($amountPaid < $orderAmount) {
                throw new \Exception('Amount paid is less than order total');
            }
    
            return true;
    
        } catch (\Exception $e) {
            Log::error('Paystack verification failed', [
                'order_id' => $orderId,
                'reference' => $reference,
                'error' => $e->getMessage(),
                'transaction_mode' => Setting::first()->transaction_mode ?? 'unknown',
                'key'=>$secretKey
            ]);
            return false;
        }
    }
    
    private function verifyFlutterwavePayment($reference, $orderId)
    {
        try {
            // Get settings and payment gateway config
            $settings = Setting::first(); 
            if (!$settings) {
                throw new \Exception('Settings not found');
            } 
            
            $flutterwaveConfig = collect($settings->payment_gateways)
                ->firstWhere('id', 'flutterwave');
    
            if (!$flutterwaveConfig || !$flutterwaveConfig['enabled']) {
                throw new \Exception('Flutterwave payment gateway is not enabled');
            }
    
            // Use test key if in test mode, live key otherwise
            $secretKey = $settings->transaction_mode === 'test'
                ? $flutterwaveConfig['secret_test_key']
                : $flutterwaveConfig['secret_key'];
    
            if (empty($secretKey)) {
                throw new \Exception('Flutterwave secret key is not configured');
            }
    
            $url = "https://api.flutterwave.com/v3/transactions/{$reference}/verify";
    
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$secretKey}",
                    'Content-Type' => 'application/json',
                ]
            ]);
    
            $responseBody = json_decode($response->getBody(), true);
            $order = Order::findOrFail($orderId);
    
            if ($responseBody['status'] === 'success' && 
                $responseBody['data']['status'] === 'successful' &&
                $responseBody['data']['amount'] >= $order->total) {
                return true;
            }
    
            Log::error('Flutterwave verification failed', [
                'order_id' => $orderId,
                'reference' => $reference,
                'response' => $responseBody,
                'order_total' => $order->total
            ]);
            return false;
    
        } catch (\Exception $e) {
            Log::error('Flutterwave verification error: ' . $e->getMessage());
            return false;
        }
    }

    public function verifyPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string',
                'reference' => 'required|string',
                'gateway' => 'required|in:paystack,flutterwave,cash'
            ]);
    
            $order = Order::findOrFail($validated['order_id']);
            
            // Find or create the payment record
            $payment = Payment::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_id' => 'PAY-' . strtoupper(uniqid()),
                    'amount' => $order->total,
                    'payment_method' => $validated['gateway'],
                    'status' => 'pending',
                ]
            );
    
            // Verify payment with the appropriate gateway
            $verified = match($validated['gateway']) {
                'paystack' => $this->verifyPaystackPayment($validated['reference'], $order->id),
                'flutterwave' => $this->verifyFlutterwavePayment($validated['reference'], $order->id),
                'cash' => true, // Cash payments are automatically verified
            };
    
            if ($verified) {
                $order->update([
                    'payment_status' => 'paid',
                    'payment_verified_at' => now(),
                ]);
    
                // Update the payment instance (not static call)
                $payment->update([
                    'status' => 'paid',
                    'verified_at' => now(),
                    'reference' => $validated['reference'],
                ]);
    
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment verified successfully'
                ]);
            }
    
            // Log failed verification with proper context array
            Log::error('Payment verification failed', [ 
                'order_id' => $order->id,
                'reference' => $validated['reference'],
                'gateway' => $validated['gateway']
            ]);
    
            return response()->json([
                'status' => 'failed',
                'message' => 'Payment verification failed'
            ], 400);
    
        } catch (\Exception $e) {
            // Log error with proper context array
            Log::error('Payment verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification error occurred'
            ], 500);
        }
    } 


}