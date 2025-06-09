<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin');
    }

    public function stats()
    {
        // Current period (this month)
        $currentStart = Carbon::now()->startOfMonth();
        $currentEnd = Carbon::now()->endOfMonth();
        
        // Previous period (last month)
        $previousStart = Carbon::now()->subMonth()->startOfMonth();
        $previousEnd = Carbon::now()->subMonth()->endOfMonth();

        // Total Revenue
        $currentRevenue = Order::whereBetween('created_at', [$currentStart, $currentEnd])
            ->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->sum('total');
        
        $previousRevenue = Order::whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->sum('total');
        $revenueChange = $previousRevenue > 0 
            ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2)
            : 100;

        // Total Orders
        $currentOrders = Order::whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $ordersChange = $previousOrders > 0 
            ? round((($currentOrders - $previousOrders) / $previousOrders) * 100, 2)
            : 100;

        // Total Customers
        $currentCustomers = User::where('role', User::ROLE_CUSTOMER)
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->count();
        $previousCustomers = User::where('role', User::ROLE_CUSTOMER)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $customersChange = $previousCustomers > 0 
            ? round((($currentCustomers - $previousCustomers) / $previousCustomers) * 100, 2)
            : 100;

        // Menu Items
        $currentProducts = Product::where('status', Product::STATUS_ACTIVE)->count();
        $previousProducts = Product::withTrashed()
            ->where('status', Product::STATUS_ACTIVE)
            ->where('created_at', '<', $currentStart)
            ->count();
        $productsChange = $previousProducts > 0 
            ? round((($currentProducts - $previousProducts) / $previousProducts) * 100, 2)
            : 100;

        return response()->json([
            'status' => 'success',
            'data' => [
                'revenue' => [
                    'current' => $currentRevenue,
                    'change' => $revenueChange,
                    'currency' => 'NGN'
                ],
                'orders' => [
                    'current' => $currentOrders,
                    'change' => $ordersChange
                ],
                'customers' => [
                    'current' => $currentCustomers,
                    'change' => $customersChange
                ],
                'products' => [
                    'current' => $currentProducts,
                    'change' => $productsChange
                ],
                'last_updated' => now()->format('l, g:i A') // e.g. "Monday, 2:30 PM"
            ]
        ]);
    }

    public function salesChart()
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $salesData = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total) as total')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill in missing dates with 0
        $results = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');
            $sale = $salesData->firstWhere('date', $dateString);
            
            $results[] = [
                'date' => $dateString,
                'total' => $sale ? $sale->total : 0
            ];
            
            $currentDate->addDay();
        }

        return response()->json([
            'status' => 'success',
            'data' => $results
        ]);
    }

    public function topSellingItems()
    {
        $topItems = OrderItem::select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(price * quantity) as total_revenue')
            )
            ->with(['product:id,title,image'])
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $topItems->map(function ($item) {
                return [
                    'id' => $item->product_id,
                    'name' => $item->product->title,
                    'image' => $item->product->image_url,
                    'quantity' => $item->total_quantity,
                    'revenue' => $item->total_revenue
                ];
            })
        ]);
    }

    public function recentOrders()
    {
        $orders = Order::with(['user:id,firstname,lastname'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'customer' => $order->user->fullname,
                    'total' => $order->total,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at->format('M d, Y H:i')
                ];
            })
        ]);
    }
}