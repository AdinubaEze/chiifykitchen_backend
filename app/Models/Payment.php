<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_id',
        'amount',
        'payment_method',
        'status',
        'reference',
        'metadata',
        'verified_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'verified_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESSFUL = 'successful';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    const METHOD_CARD = 'card';
    const METHOD_PAYSTACK = 'paystack';
    const METHOD_FLUTTERWAVE = 'flutterwave';
    const METHOD_CASH = 'cash';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public static function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SUCCESSFUL => 'Successful',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    public static function getMethodOptions()
    {
        return [
            self::METHOD_CARD => 'Card',
            self::METHOD_PAYSTACK => 'Paystack',
            self::METHOD_FLUTTERWAVE => 'Flutterwave',
            self::METHOD_CASH => 'Cash',
        ];
    }
}