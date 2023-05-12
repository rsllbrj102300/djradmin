<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'tags';
    protected $fillable = ['tag'];

    public function products()
    {
        return $this->belongsToMany(Product::class)->using('App\Model\ProductTag');
    }
}
