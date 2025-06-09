<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'payment_gateways',
        'transaction_mode',
        'company_info',
        'notifications',
        'general_settings'
    ];

    protected $casts = [
        'payment_gateways' => 'array',
        'company_info' => 'array',
        'notifications' => 'array',
        'general_settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'payment_gateways' => '[
            {
                "id": "paystack",
                "name": "Paystack",
                "enabled": false,
                "public_key": null,
                "public_test_key":null,
                "secret_key": null,
                "secret_test_key": null,
                "logo": null
            },
            {
                "id": "flutterwave",
                "name": "Flutterwave",
                "enabled": false,
                 "public_key": null,
                "public_test_key":null,
                "secret_key": null,
                "secret_test_key": null,
                "logo": null
            }
        ]',
        'transaction_mode' => 'test',
        'company_info' => '{
            "name": "",
            "email": "",
            "phone": "",
            "website": null,
            "address": null,
            "logo": null
        }',
        'notifications' => '{
            "email_notifications": true,
            "sms_notifications": false,
            "push_notifications": true,
            "order_updates": true,
            "promotional_emails": false
        }',
        'general_settings' => '{
            "currency": "NGN",
            "tax_rate": 7.5,
            "delivery_fee": 0,
            "minimum_order_amount": 0
        }'
    ];
}