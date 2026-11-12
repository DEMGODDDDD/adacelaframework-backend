<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'description',
        'price',
        'sale_price',
        'stock_quantity',
        'brand',
        'status',
        'featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'status' => 'boolean',
        'featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (Product $product): void {
            $product->inventory()->updateOrCreate(
                [],
                [
                    'stock_quantity' => $product->stock_quantity,
                    'updated_at' => now(),
                ]
            );
        });
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->where('moderation_status', 'approved');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function getFinalPriceAttribute()
    {
        return $this->sale_price !== null && $this->sale_price < $this->price
            ? $this->sale_price
            : $this->price;
    }

    public function getInStockAttribute()
    {
        return $this->stock_quantity > 0;
    }
}