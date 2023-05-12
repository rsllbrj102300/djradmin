<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class FavoriteProduct extends Model
{
    public function products()
    {
        return $this->hasMany(Product::class, 'id', 'product_id');
    }
}
