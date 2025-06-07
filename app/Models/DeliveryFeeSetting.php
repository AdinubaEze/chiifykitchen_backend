<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryFeeSetting extends Model
{
    protected $fillable = ['delivery_type', 'fee'];
    
    public static function getFeeForType($type)
    {
        return static::where('delivery_type', $type)->value('fee') ?? 0;
    }
}