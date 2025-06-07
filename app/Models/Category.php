<?php

// app/Models/Category.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; 
 
class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'icon_image',
        'status',
        'admin_id'
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

     
    protected $appends = ['icon_image_url'];
    
    public function getIconImageUrlAttribute()
    {
        if ($this->icon_image) {
            return asset($this->icon_image);
        }
        return null;
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function getStatusOptions()
    {
        return [
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ACTIVE => 'Active',
        ];
    }
 
    
}