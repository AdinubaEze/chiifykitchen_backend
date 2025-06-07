<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DELETED = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_DISABLED = 2;

    protected $fillable = [
        'title',
        'price',
        'discounted_price',
        'image',
        'description',
        'status',
        'is_featured',
        'category_id',
        'admin_id'
    ];

    protected $casts = [
        'status' => 'integer',
        'is_featured' => 'boolean',
        'price' => 'float',
        'discounted_price' => 'float'
    ];

    protected $appends = ['image_url', 'images_urls'];

    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset($this->image);
        }
        return null;
    }

    public function getImagesUrlsAttribute()
    {
        return $this->productImages->map(function($image) {
            return asset($image->path);
        });
    }

    public static function getStatusOptions()
    {
        return [
            self::STATUS_DELETED => 'Deleted',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_DISABLED => 'Disabled',
        ];
    }
}