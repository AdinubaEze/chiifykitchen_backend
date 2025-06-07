<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'order_id' => 'ORD-' . strtoupper($this->faker->unique()->bothify('????####')),
            'user_id' => User::factory(),
            'address_id' => Address::factory(),
            'table_id' => $this->faker->boolean(50) ? Table::factory() : null,
            'subtotal' => $this->faker->randomFloat(2, 10, 500),
            'delivery_fee' => $this->faker->randomFloat(2, 0, 20),
            'total' => $this->faker->randomFloat(2, 10, 520),
            'payment_method' => $this->faker->randomElement(['card', 'cash']),
            'delivery_method' => $this->faker->randomElement(Order::getDeliveryMethodOptions()),
            'payment_status' => $this->faker->randomElement(Order::getPaymentStatusOptions()),
            'status' => $this->faker->randomElement(Order::getStatusOptions()),
        ];
    }
}