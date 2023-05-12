<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
//use Illuminate\Database\Eloquent\SoftDeletes;

class RecentSearch extends Model
{
    //use SoftDeletes;

    protected $casts = [];

    protected $fillable = [
        'keyword',
    ];

    public function response_data_count()
    {
        return $this->hasOne(SearchedData::class, 'attribute_id');
    }

    public function volume()
    {
        return $this->hasOne(SearchedKeywordCount::class, 'recent_search_id', 'id');
    }

    public function searched_category()
    {
        return $this->hasMany(SearchedCategory::class, 'recent_search_id', 'id');
    }

    public function searched_product()
    {
        return $this->hasMany(SearchedProduct::class, 'recent_search_id', 'id');
    }

    public function searched_user()
    {
        return $this->hasMany(SearchedKeywordUser::class, 'recent_search_id', 'id');
    }
}
