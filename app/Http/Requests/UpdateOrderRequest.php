<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; 

class UpdateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => ['sometimes', Rule::in(Order::getStatusOptions())],
            'payment_status' => ['sometimes', Rule::in(Order::getPaymentStatusOptions())],
            'address_id' => [
                'sometimes',
                'exists:addresses,id,user_id,'.$this->order->user_id,
                function ($attribute, $value, $fail) {
                    if (!$this->order->canEditAddress()) {
                        $fail('Address can only be changed when order is pending.');
                    }
                }
            ],
        ];
    }
}