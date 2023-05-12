<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Model\CategorySearchedByUser;
use App\Model\FavoriteProduct;
use App\Model\Product;
use App\Model\ProductSearchedByUser;
use App\Model\RecentSearch;
use App\Model\Review;
use App\Model\SearchedCategory;
use App\Model\SearchedData;
use App\Model\SearchedKeywordCount;
use App\Model\SearchedKeywordUser;
use App\Model\SearchedProduct;
use App\Model\Translation;
use App\User;
use App\VisitedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function get_latest_products(Request $request)
    {
        $products = ProductLogic::get_latest_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_searched_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $products = ProductLogic::search_products($request['name'], $request['limit'], $request['offset']);
        if (count($products['products']) == 0) {
            $key = explode(' ', $request['name']);
            $ids = Translation::where(['key' => 'name'])->where(function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->orWhere('value', 'like', "%{$value}%");
                }
            })->pluck('translationable_id')->toArray();
            $paginator = Product::active()->whereIn('id', $ids)->withCount(['wishlist'])->with(['rating'])
                ->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $products = [
                'total_size' => $paginator->total(),
                'limit' => $request['limit'],
                'offset' => $request['offset'],
                'products' => $paginator->items()
            ];
        }

        //search log
        $auth_user = auth('api')->user();
        $keyword = strtolower($request['name']);

        $recent_search = RecentSearch::firstOrCreate(['keyword' => $keyword], [
            'keyword' => $keyword,
        ]);

        $recent_search_user = new SearchedKeywordUser();
        $recent_search_user->recent_search_id = $recent_search->id;
        $recent_search_user->user_id = $auth_user->id ?? null;
        $recent_search_user->save();

        $searched_count = new SearchedKeywordCount();
        $searched_count->recent_search_id = $recent_search->id;
        $searched_count->keyword_count = 1;
        $searched_count->save();

        $category_ids = [];
        foreach ($products['products'] as $searched_result){
            $categories =  json_decode($searched_result['category_ids']);
            if(!is_null($categories) && count($categories) > 0) {
                foreach ($categories as $value) {
                    if ($value->position == 1) {
                        $category_ids[] = $value->id;
                    }
                }
            }

            $searched_product_data = SearchedProduct::firstOrCreate([
                'recent_search_id' => $recent_search->id,
                'product_id' => $searched_result->id
            ], [
                'recent_search_id' => $recent_search->id,
                'product_id' => $searched_result->id
            ]);

            if (auth('api')->user()){
                $product_searched_by_user = ProductSearchedByUser::firstOrCreate([
                    'user_id' => $auth_user->id,
                    'product_id' => $searched_result->id
                ], [
                    'user_id' => $auth_user->id,
                    'product_id' => $searched_result->id
                ]);
            }
        }

        $category_ids = array_unique($category_ids);
        foreach ($category_ids as $cat_id){
            $searched_category_data = SearchedCategory::firstOrCreate([
                'recent_search_id' => $recent_search->id,
                'category_id' => $cat_id,
            ], [
                'recent_search_id' => $recent_search->id,
                'category_id' => $cat_id,
            ]);

            if (auth('api')->user()){
                $category_searched_by_user = CategorySearchedByUser::firstOrCreate([
                    'user_id' => $auth_user->id,
                    'category_id' => $cat_id
                ], [
                    'user_id' => $auth_user->id,
                    'category_id' => $cat_id
                ]);
            }

        }

        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_product(Request $request, $id)
    {
        try {
            $product = ProductLogic::get_product($id);
            if (!isset($product)) {
                return response()->json(['errors' => ['code' => 'product-001', 'message' => 'Product not found!']], 404);
            }

            $product = Helpers::product_data_formatting($product, false);

            $product->increment('view_count');

            if($request->has('attribute') && $request->attribute == 'product' && !is_null(auth('api')->user())) {

                $visited_product = new VisitedProduct();
                $visited_product->user_id = auth('api')->user()->id ?? null;
                $visited_product->product_id = $product->id;
                $visited_product->save();
            }

            return response()->json($product, 200);

        } catch (\Exception $e) {
            return response()->json(['errors' => ['code' => 'product-001', 'message' => 'Product not found!']], 404);
        }
    }

    public function get_related_products($id)
    {
        if (Product::find($id)) {
            $products = ProductLogic::get_related_products($id);
            $products = Helpers::product_data_formatting($products, true);
            return response()->json($products, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => 'Product not found!'],
        ], 404);
    }

    public function get_product_reviews($id)
    {
        $reviews = Review::with(['customer'])->where(['product_id' => $id])->get();

        $storage = [];
        foreach ($reviews as $item) {
            $item['attachment'] = json_decode($item['attachment']);
            array_push($storage, $item);
        }

        return response()->json($storage, 200);
    }

    public function get_product_rating($id)
    {
        try {
            $product = Product::find($id);
            $overallRating = ProductLogic::get_overall_rating($product->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function submit_product_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'order_id' => 'required',
            'comment' => 'required',
            'rating' => 'required|numeric|max:5',
        ]);

        $product = Product::find($request->product_id);
        if (isset($product) == false) {
            $validator->errors()->add('product_id', 'There is no such product');
        }

        $multi_review = Review::where(['product_id' => $request->product_id, 'user_id' => $request->user()->id])->first();
        if (isset($multi_review)) {
            $review = $multi_review;
        } else {
            $review = new Review;
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image_array = [];
        if (!empty($request->file('attachment'))) {
            foreach ($request->file('attachment') as $image) {
                if ($image != null) {
                    if (!Storage::disk('public')->exists('review')) {
                        Storage::disk('public')->makeDirectory('review');
                    }
                    array_push($image_array, Storage::disk('public')->put('review', $image));
                }
            }
        }

        $review->user_id = $request->user()->id;
        $review->product_id = $request->product_id;
        $review->order_id = $request->order_id;
        $review->comment = $request->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($image_array);
        $review->save();

        return response()->json(['message' => 'successfully review submitted!'], 200);
    }

    public function get_discounted_products()
    {
        try {
            $products = Helpers::product_data_formatting(Product::active()->withCount(['wishlist'])->with(['rating'])->where('discount', '>', 0)->get(), true);
            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Set menu not found!'],
            ], 404);
        }
    }

    public function get_daily_need_products(Request $request)
    {
        try {
            $paginator = Product::active()->withCount(['wishlist'])->with(['rating'])->where(['daily_needs' => 1])->orderBy('id', 'desc')->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $products = [
                'total_size' => $paginator->total(),
                'limit' => $request['limit'],
                'offset' => $request['offset'],
                'products' => $paginator->items()
            ];
            $paginator = Helpers::product_data_formatting($products['products'], true);


            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Products not found!'],
            ], 404);
        }
    }

    //favorite products
    public function get_favorite_products(Request $request)
    {
        $products = ProductLogic::get_favorite_products($request['limit'], $request['offset'], $request->user()->id);
        return response()->json($products, 200);
    }

    public function get_popular_products(Request $request)
    {
        $products = ProductLogic::get_popular_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);

    }

    public function add_favorite_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
        ],
            [
                'product_ids.required' => 'product_ids ' .translate('is required'),
                'product_ids.array' => 'product_ids ' .translate('must be an array')
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $favorite_ids = [];
        foreach ($request->product_ids as $id) {
            $values = [
                'user_id' => $request->user()->id,
                'product_id' => $id,
                'created_at' => now(),
                'updated_at' => now()
            ];
            array_push($favorite_ids, $values);
        }
        FavoriteProduct::insert($favorite_ids);

        return response()->json(['message' => translate('Item added to favourite list!')], 200);
    }

    public function remove_favorite_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
        ],
            [
                'product_ids.required' => 'product_ids ' .translate('is required'),
                'product_ids.array' => 'product_ids ' .translate('must be an array')
            ]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $collection = FavoriteProduct::whereIn('product_id', $request->product_ids)->get(['id']);
        FavoriteProduct::destroy($collection->toArray());

        return response()->json(['message' => translate('Item removed from favourite list! ')], 200);
    }

    public function featured_products(Request $request)
    {
        try {
            $paginator = Product::active()
                ->withCount(['wishlist'])
                ->with(['rating'])
                ->where(['is_featured' => 1])
                ->orderBy('id', 'desc')
                ->paginate($request['limit'], ['*'], 'page', $request['offset']);

            $products = [
                'total_size' => $paginator->total(),
                'limit' => $request['limit'],
                'offset' => $request['offset'],
                'products' => $paginator->items()
            ];
            $paginator = Helpers::product_data_formatting($products['products'], true);


            return response()->json($products, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Products not found!'],
            ], 404);
        }
    }

    public function get_most_viewed_products(Request $request)
    {
        $products = ProductLogic::get_most_viewed_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_trending_products(Request $request)
    {
        $products = ProductLogic::get_trending_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_recommended_products(Request $request)
    {
        $user = $request->user();
        $products = ProductLogic::get_recommended_products($user, $request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_most_reviewed_products(Request $request)
    {
        $products = ProductLogic::get_most_reviewed_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }


}
