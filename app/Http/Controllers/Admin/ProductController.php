<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\BusinessSetting;
use App\Model\Category;
use App\Model\Product;
use App\Model\Review;
use App\Model\Tag;
use App\Model\Translation;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Rap2hpoutre\FastExcel\FastExcel;

class ProductController extends Controller
{
    public function variant_combination(Request $request)
    {
        $options = [];
        $price = $request->price;
        $product_name = $request->name;

        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }

        $result = [[]];
        foreach ($options as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }
        $combinations = $result;
        return response()->json([
            'view' => view('admin-views.product.partials._variant-combinations', compact('combinations', 'price', 'product_name'))->render(),
        ]);
    }

    public function get_categories(Request $request)
    {
        $cat = Category::where(['parent_id' => $request->parent_id])->get();
        $res = '<option value="' . 0 . '" disabled selected>---Select---</option>';
        foreach ($cat as $row) {
            if ($row->id == $request->sub_category) {
                $res .= '<option value="' . $row->id . '" selected >' . $row->name . '</option>';
            } else {
                $res .= '<option value="' . $row->id . '">' . $row->name . '</option>';
            }
        }
        return response()->json([
            'options' => $res,
        ]);
    }

    public function index()
    {
        $categories = Category::where(['position' => 0])->get();
        return view('admin-views.product.index', compact('categories'));
    }

    public function list(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = Product::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('name', 'like', "%{$value}%");
                }
            })->latest();
            $query_param = ['search' => $request['search']];
        }else{
            $query = Product::latest();
        }
        $products = $query->with('order_details.order')->paginate(Helpers::getPagination())->appends($query_param);

        foreach ($products as $product) {
            $total_sold = 0;
            foreach ($product->order_details as $detail) {
                if ($detail->order->order_status == 'delivered'){
                    $total_sold += $detail->quantity;
                }
            }
             $product->total_sold = $total_sold;
        }

        return view('admin-views.product.list', compact('products','search'));
    }

    public function search(Request $request)
    {
        $key = explode(' ', $request['search']);
        $products = Product::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
        })->get();
        return response()->json([
            'view' => view('admin-views.product.partials._table', compact('products'))->render(),
        ]);
    }

    public function view($id)
    {
        $product = Product::where(['id' => $id])->first();
        $reviews = Review::where(['product_id' => $id])->latest()->paginate(20);
        return view('admin-views.product.view', compact('product', 'reviews'));
    }

    public function store(Request $request)
    {
        //dd($request->status);
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:products',
            'category_id' => 'required',
            'images' => 'required',
            'total_stock' => 'required|numeric|min:1',
            'price' => 'required|numeric|min:0',
        ], [
            'name.required' => translate('Product name is required!'),
            'name.unique' => translate('Product name must be unique!'),
            'category_id.required' => translate('category  is required!'),
        ]);

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            $validator->getMessageBag()->add('unit_price', 'Discount can not be more or equal to the price!');
        }

        $img_names = [];
        if (!empty($request->file('images'))) {
            foreach ($request->images as $img) {
                $image_data = Helpers::upload('product/', 'png', $img);
                $img_names[] = $image_data;
            }
            $image_data = json_encode($img_names);
        } else {
            $image_data = json_encode([]);
        }

        $tag_ids = [];
        if ($request->tags != null) {
            $tags = explode(",", $request->tags);
        }
        if(isset($tags)){
            foreach ($tags as $key => $value) {
                $tag = Tag::firstOrNew(
                    ['tag' => $value]
                );
                $tag->save();
                $tag_ids[] = $tag->id;
            }
        }
        //dd($tag_ids);

        $p = new Product;
        $p->name = $request->name[array_search('en', $request->lang)];

        $category = [];
        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }

        $p->category_ids = json_encode($category);
        $p->description = $request->description[array_search('en', $request->lang)];

        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                if ($request[$str][0] == null) {
                    $validator->getMessageBag()->add('name', 'Attribute choice option values can not be null!');
                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', $request[$str])));
                $choice_options[] = $item;
            }
        }

        $p->choice_options = json_encode($choice_options);
        $variations = [];
        $options = [];
        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }
        //Generates the combinations of customer choice options
        $combinations = Helpers::combinations($options);

        $stock_count = 0;
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        $str .= str_replace(' ', '', $item);
                    }
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $item['stock'] = abs($request['stock_' . str_replace('.', '_', $str)]);

                if ($request['discount_type'] == 'amount' && $item['price'] <= $request['discount'] ){
                    $validator->getMessageBag()->add('discount_mismatch', 'Discount can not be more or equal to the price. Please change variant '. $item['type'] .' price or change discount amount!');
                }

                array_push($variations, $item);
                $stock_count += $item['stock'];
            }
        } else {
            $stock_count = (integer)$request['total_stock'];
        }

        if ((integer)$request['total_stock'] != $stock_count) {
            $validator->getMessageBag()->add('total_stock', 'Stock calculation mismatch!');
        }

        if ($validator->getMessageBag()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        //combinations end
        $p->variations = json_encode($variations);
        $p->price = $request->price;
        $p->unit = $request->unit;
        $p->image = $image_data;
        $p->capacity = $request->capacity;
        $p->maximum_order_quantity = $request->maximum_order_quantity;
        // $p->set_menu = $request->item_type;

        $p->tax = $request->tax_type == 'amount' ? $request->tax : $request->tax;
        $p->tax_type = $request->tax_type;

        $p->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $p->discount_type = $request->discount_type;
        $p->total_stock = $request->total_stock;

        $p->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $p->status = $request->status? $request->status:0;

        $p->save();

        $p->tags()->sync($tag_ids);

        $data = [];
        foreach($request->lang as $index=>$key)
        {
            if($request->name[$index] && $key != 'en')
            {
                $data[] = array(
                    'translationable_type' => 'App\Model\Product',
                    'translationable_id' => $p->id,
                    'locale' => $key,
                    'key' => 'name',
                    'value' => $request->name[$index],
                );
            }
            if($request->description[$index] && $key != 'en')
            {
                $data[] = array(
                    'translationable_type' => 'App\Model\Product',
                    'translationable_id' => $p->id,
                    'locale' => $key,
                    'key' => 'description',
                    'value' => $request->description[$index],
                );
            }
        }


        Translation::insert($data);

        return response()->json([], 200);
    }

    public function edit($id)
    {
        $product = Product::withoutGlobalScopes()->with('translations')->find($id);
        $product_category = json_decode($product->category_ids);
        $categories = Category::where(['parent_id' => 0])->get();
        return view('admin-views.product.edit', compact('product', 'product_category', 'categories'));
    }

    public function status(Request $request)
    {
        $product = Product::find($request->id);
        $product->status = $request->status;
        $product->save();
        Toastr::success(translate('Product status updated!'));
        return back();
    }

    public function feature(Request $request)
    {
        $product = Product::find($request->id);
        $product->is_featured = $request->is_featured;
        $product->save();
        Toastr::success(translate('product feature status updated!'));
        return back();
    }

    public function daily_needs(Request $request)
    {
        $product = Product::find($request->id);
        $product->daily_needs = $request->status;
        $product->save();
        return response()->json([], 200);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:products,name,'.$request->id,
            'category_id' => 'required',
            'total_stock' => 'required|numeric|min:1',
            'price' => 'required|numeric|min:0',
        ], [
            'name.required' => 'Product name is required!',
            'category_id.required' => 'category  is required!',
        ]);

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            $validator->getMessageBag()->add('unit_price', 'Discount can not be more or equal to the price!');
        }

        $tag_ids = [];
        if ($request->tags != null) {
            $tags = explode(",", $request->tags);
        }
        if(isset($tags)){
            foreach ($tags as $key => $value) {
                $tag = Tag::firstOrNew(
                    ['tag' => $value]
                );
                $tag->save();
                $tag_ids[] = $tag->id;
            }
        }

        $p = Product::find($id);

        $images = json_decode($p->image);
        if (!empty($request->file('images'))) {
            foreach ($request->images as $img) {
                $image_data = Helpers::upload('product/', 'png', $img);
                $images[] = $image_data;
            }

        }

        if (!count($images)) {
            $validator->getMessageBag()->add('images', 'Image can not be empty!');
        }

        $p->name = $request->name[array_search('en', $request->lang)];

        $category = [];
        if ($request->category_id != null) {
            $category[] = [
                'id' => $request->category_id,
                'position' => 1,
            ];
        }
        if ($request->sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_category_id,
                'position' => 2,
            ];
        }
        if ($request->sub_sub_category_id != null) {
            $category[] = [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ];
        }

        $p->category_ids = json_encode($category);
        $p->description = $request->description[array_search('en', $request->lang)];

        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_' . $no;
                if ($request[$str][0] == null) {
                    $validator->getMessageBag()->add('name', 'Attribute choice option values can not be null!');
                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $item['name'] = 'choice_' . $no;
                $item['title'] = $request->choice[$key];
                $item['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', $request[$str])));
                $choice_options[] = $item;
            }
        }
        $p->choice_options = json_encode($choice_options);
        $variations = [];
        $options = [];
        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', $request[$name]);
                $options[] = explode(',', $my_str);
            }
        }

        //Generates the combinations of customer choice options
        $combinations = Helpers::combinations($options);
        $stock_count = 0;
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $item) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $item);
                    } else {
                        $str .= str_replace(' ', '', $item);
                    }
                }
                $item = [];
                $item['type'] = $str;
                $item['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $item['stock'] = abs($request['stock_' . str_replace('.', '_', $str)]);

                if ($request['discount_type'] == 'amount' && $item['price'] <= $request['discount'] ){
                    $validator->getMessageBag()->add('discount_mismatch', 'Discount can not be more or equal to the price. Please change variant '. $item['type'] .' price or change discount amount!');
                }

                array_push($variations, $item);
                $stock_count += $item['stock'];
            }
        } else {
            $stock_count = (integer)$request['total_stock'];
        }

        if ((integer)$request['total_stock'] != $stock_count) {
            $validator->getMessageBag()->add('total_stock', 'Stock calculation mismatch!');
        }

        if ($validator->getMessageBag()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        //combinations end
        $p->variations = json_encode($variations);
        $p->price = $request->price;
        $p->capacity = $request->capacity;
        $p->unit = $request->unit;
        $p->maximum_order_quantity = $request->maximum_order_quantity;

        // $p->image = json_encode(array_merge(json_decode($p['image'], true), json_decode($image_data, true)));
        // $p->set_menu = $request->item_type;
        $p->image = json_encode($images);
        // $p->available_time_starts = $request->available_time_starts;
        // $p->available_time_ends = $request->available_time_ends;

        $p->tax = $request->tax_type == 'amount' ? $request->tax : $request->tax;
        $p->tax_type = $request->tax_type;

        $p->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $p->discount_type = $request->discount_type;
        $p->total_stock = $request->total_stock;

        $p->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $p->status = $request->status? $request->status:0;

        $p->save();

        $p->tags()->sync($tag_ids);

        foreach($request->lang as $index=>$key)
        {
            if($request->name[$index] && $key != 'en')
            {
                Translation::updateOrInsert(
                    ['translationable_type'  => 'App\Model\Product',
                        'translationable_id'    => $p->id,
                        'locale'                => $key,
                        'key'                   => 'name'],
                    ['value'                 => $request->name[$index]]
                );
            }
            if($request->description[$index] && $key != 'en')
            {
                Translation::updateOrInsert(
                    ['translationable_type'  => 'App\Model\Product',
                        'translationable_id'    => $p->id,
                        'locale'                => $key,
                        'key'                   => 'description'],
                    ['value'                 => $request->description[$index]]
                );
            }
        }

        return response()->json([], 200);
    }

    public function delete(Request $request)
    {
        $product = Product::find($request->id);
        foreach (json_decode($product['image'], true) as $img) {
            if (Storage::disk('public')->exists('product/' . $img)) {
                Storage::disk('public')->delete('product/' . $img);
            }
        }

        $product->delete();
        Toastr::success(translate('Product removed!'));
        return back();
    }

    public function remove_image($id, $name)
    {
        if (Storage::disk('public')->exists('product/' . $name)) {
            Storage::disk('public')->delete('product/' . $name);
        }

        $product = Product::find($id);
        $img_arr = [];
        // if (count(json_decode($product['images'])) < 2) {
        //     Toastr::warning('You cannot delete all images!');
        //     return back();
        // }

        foreach (json_decode($product['image'], true) as $img) {
            if (strcmp($img, $name) != 0) {
                array_push($img_arr, $img);
            }
        }

        Product::where(['id' => $id])->update([
            'image' => json_encode($img_arr),
        ]);
        Toastr::success(translate('Image removed successfully!'));
        return back();
    }

    public function bulk_import_index()
    {
        return view('admin-views.product.bulk-import');
    }

    public function bulk_import_data(Request $request)
    {
        try {
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (\Exception $exception) {
            Toastr::error(translate('You have uploaded a wrong format file, please upload the right file.'));
            return back();
        }
        $col_key = ['name','description','price','tax','category_id','sub_category_id','discount','discount_type','tax_type','unit','total_stock','capacity','daily_needs'];
        foreach ($collections as $key => $collection) {

            foreach ($collection as $key => $value) {
                if ($key!="" && !in_array($key, $col_key)) {
                    Toastr::error('Please upload the correct format file.');
                    return back();
                }
            }

            /*if ($collection['name'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: name ');
                return back();
            } elseif ($collection['description'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: description ');
                return back();
            } elseif (!is_numeric($collection['price'])) {
                Toastr::error('Price of row ' . ($key + 2) . ' must be number');
                return back();
            } elseif (!is_numeric($collection['tax'])) {
                Toastr::error('Tax of row ' . ($key + 2) . ' must be number');
                return back();
            } elseif ($collection['price'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: price ');
                return back();
            } elseif ($collection['category_id'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: category_id ');
                return back();
            } elseif ($collection['sub_category_id'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: sub_category_id ');
                return back();
            } elseif (!is_numeric($collection['discount'])) {
                Toastr::error('Discount of row ' . ($key + 2) . ' must be number');
                return back();
            } elseif ($collection['discount'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: discount ');
                return back();
            } elseif ($collection['discount_type'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: discount_type ');
                return back();
            } elseif ($collection['tax_type'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: tax_type ');
                return back();
            } elseif ($collection['unit'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: unit ');
                return back();
            } elseif (!is_numeric($collection['total_stock'])) {
                Toastr::error('Total Stock of row ' . ($key + 2) . ' must be number');
                return back();
            } elseif ($collection['total_stock'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: total_stock ');
                return back();
            } elseif (!is_numeric($collection['capacity'])) {
                Toastr::error('Capacity of row ' . ($key + 2) . ' must be number');
                return back();
            } elseif ($collection['capacity'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: capacity ');
                return back();
            } elseif (!is_numeric($collection['daily_needs'])) {
                Toastr::error('Daily Needs of row ' . ($key + 2) . ' must be number');
                return back();
            } elseif ($collection['daily_needs'] === "") {
                Toastr::error('Please fill row:' . ($key + 2) . ' field: daily_needs ');
                return back();
            }

            $product = [
                'discount_type' => $collection['discount_type'],
                'discount' => $collection['discount'],
            ];
            if ($collection['price'] <= Helpers::discount_calculate($product, $collection['price'])) {
                Toastr::error('Discount can not be more or equal to the price in row '. ($key + 2));
                return back();
            }*/
        }
        $data = [];
        foreach ($collections as $collection) {

            array_push($data, [
                'name' => $collection['name'],
                'description' => $collection['description'],
                'image' => json_encode(['def.png']),
                'price' => $collection['price'],
                'variations' => json_encode([]),
                'tax' => $collection['tax'],
                'status' => 1,
                'attributes' => json_encode([]),
                'category_ids' => json_encode([['id' => (string)$collection['category_id'], 'position' => 0], ['id' => (string)$collection['sub_category_id'], 'position' => 1]]),
                'choice_options' => json_encode([]),
                'discount' => $collection['discount'],
                'discount_type' => $collection['discount_type'],
                'tax_type' => $collection['tax_type'],
                'unit' => $collection['unit'],
                'total_stock' => $collection['total_stock'],
                'capacity' => $collection['capacity'],
                'daily_needs' => $collection['daily_needs'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        DB::table('products')->insert($data);
        Toastr::success(count($data) . (translate(' - Products imported successfully!')));
        return back();
    }

    public function bulk_export_index()
    {
        return view('admin-views.product.bulk-export-index');
    }

    public function bulk_export_data(Request $request)
    {
        $start_date = $request->type == 'date_wise' ? $request['start_date'] : null;
        $end_date = $request->type == 'date_wise' ? $request['end_date'] : null;

        //dd($start_date, $end_date);

        $products = Product::when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->get();

        $storage = [];
        foreach($products as $item){
            $category_id = 0;
            $sub_category_id = 0;

            foreach(json_decode($item->category_ids, true) as $category)
            {
                if($category['position']==1)
                {
                    $category_id = $category['id'];
                }
                else if($category['position']==2)
                {
                    $sub_category_id = $category['id'];
                }
            }

            if (!isset($item['description'])) {
                $item['description'] = 'No description available';
            }

            if (!isset($item['capacity'])) {
                $item['capacity'] = 0;
            }

            $storage[] = [
                'name' => $item['name'],
                'description' => $item['description'],
                'price' => $item['price'],
                'tax' => $item['tax'],
                'category_id'=>$category_id,
                'sub_category_id'=>$sub_category_id,
                'discount'=>$item['discount'],
                'discount_type'=>$item['discount_type'],
                'tax_type'=>$item['tax_type'],
                'unit'=>$item['unit'],
                'total_stock'=>$item['total_stock'],
                'capacity'=>$item['capacity'],
                'daily_needs'=>$item['daily_needs'],
            ];

        }
        return (new FastExcel($storage))->download('products.xlsx');
    }

    public function limited_stock(Request $request)
    {
        $stock_limit = BusinessSetting::where('key','minimum_stock_limit')->first()->value;
        $query_param = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = Product::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('name', 'like', "%{$value}%");
                }
            })->where('total_stock', '<', $stock_limit)->latest();
            $query_param = ['search' => $request['search']];
        }else{
            $query = Product::where('total_stock', '<', $stock_limit)->latest();
        }

        $products = $query->paginate(Helpers::getPagination())->appends($query_param);

        return view('admin-views.product.limited-stock', compact('products', 'search', 'stock_limit'));
    }

    public function get_variations(Request $request)
    {
        $product = Product::find($request['id']);
        return response()->json([
            'view' => view('admin-views.product.partials._update_stock', compact('product'))->render()
        ]);
    }

    public function update_quantity(Request $request)
    {
        //dd($request->all());
        $variations = [];
        $stock_count = $request['total_stock'];
        $product_price = $request['product_price'];
        if ($request->has('type')) {
            foreach ($request['type'] as $key => $str) {
                $item = [];
                $item['type'] = $str;
                //$item['price'] = BackEndHelper::currency_to_usd(abs($request['price_' . str_replace('.', '_', $str)]));
                $item['price'] = (abs($request['price_' . str_replace('.', '_', $str)]));
                $item['stock'] = abs($request['qty_' . str_replace('.', '_', $str)]);
                array_push($variations, $item);
            }
        }

        $product = Product::find($request['product_id']);
        if ($stock_count >= 0) {
            $product->total_stock = $stock_count;
            $product->variations = json_encode($variations);
            $product->save();
            Toastr::success(translate('product_quantity_updated_successfully!'));
            return back();
        } else {
            Toastr::warning(translate('product_quantity_can_not_be_less_than_0_!'));
            return back();
        }
    }

}
