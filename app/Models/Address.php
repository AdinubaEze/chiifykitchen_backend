<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; 

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'street',
        'city',
        'state',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}