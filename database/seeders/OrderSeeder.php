<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run()
    {
        Order::factory(50)->create()->each(function ($order) {
            $products = Product::inRandomOrder()->limit(rand(1, 5))->get();
            
            foreach ($products as $product) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => rand(1, 5),
                    'price' => $product->price,
                    'discounted_price' => $product->discounted_price,
                ]);
            }

            // Recalculate order totals based on items
            $subtotal = $order->items->sum(function ($item) {
                return ($item->discounted_price ?? $item->price) * $item->quantity;
            });

            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + $order->delivery_fee,
            ]);
        });
    }
}