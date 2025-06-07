<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'user' => new UserResource($this->whenLoaded('user')),
            'address' => new AddressResource($this->whenLoaded('address')),
            'table' => new TableResource($this->whenLoaded('table')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'delivery_fee' => $this->delivery_fee,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'note'=>$this->note,
            'payment_method' => $this->payment_method,
            'delivery_method' => $this->delivery_method,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can_edit_address' => $this->canEditAddress(),
        ];
    }
}