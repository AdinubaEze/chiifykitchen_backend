<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_DELIVERED = 'delivered';

    const DELIVERY_METHOD_DELIVERY = 'delivery';
    const DELIVERY_METHOD_DINE_IN = 'dine-in';
    const DELIVERY_METHOD_PICKUP = 'pickup';
    const DELIVERY_METHOD_COURIER = 'courier';

    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_UNPAID = 'unpaid';
    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    const PAYMENT_METHOD_CASH = 'cash';

    protected $fillable = [
        'order_id',
        'user_id',
        'address_id',
        'table_id',
        'admin_id',
        'subtotal',
        'delivery_fee',
        'total',
        'payment_method',
        'delivery_method',
        'payment_status',
        'status',
        'cancelled_by_customer',
        'customer_cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public static function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => 'pending',
            self::STATUS_PROCESSING => 'processing',
            self::STATUS_COMPLETED => 'completed',
            self::STATUS_CANCELLED => 'cancelled',
            self::STATUS_DELIVERED=>'delivered',
        ];
    }

    public static function getDeliveryMethodOptions()
    {
        return [
            self::DELIVERY_METHOD_DELIVERY => 'delivery',
            self::DELIVERY_METHOD_DINE_IN => 'dine-in',
            self::DELIVERY_METHOD_PICKUP => 'pickup',
            self::DELIVERY_METHOD_COURIER => 'courier',
        ];
    }

    public static function getPaymentStatusOptions()
    {
        return [
            self::PAYMENT_STATUS_PAID => 'paid',
            self::PAYMENT_STATUS_UNPAID => 'unpaid',
            self::PAYMENT_STATUS_PENDING => 'pending',
            self::PAYMENT_STATUS_FAILED => 'failed',
            self::PAYMENT_STATUS_REFUNDED => 'refunded',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['quantity', 'price', 'discounted_price'])
            ->withTimestamps();
    }
     public function payment()
    {
        return $this->hasOne(Payment::class);
    }
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canEditAddress()
    {
        return $this->isPending();
    }

    public function isCashPaymentAllowed()
    {
        return $this->delivery_method === self::DELIVERY_METHOD_DINE_IN;
    }
}