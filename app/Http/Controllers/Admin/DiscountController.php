<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Banner;
use App\Model\Category;
use App\Model\CategoryDiscount;
use App\Model\Product;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DiscountController extends Controller
{
    function index(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $discounts = CategoryDiscount::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            })->orderBy('id', 'desc');
            $query_param = ['search' => $request['search']];
        }else{
            $discounts = CategoryDiscount::orderBy('id', 'desc');
        }
        $discounts = $discounts->paginate(Helpers::getPagination())->appends($query_param);

        $categories = Category::where(['parent_id'=>0])->orderBy('name')->get();
        return view('admin-views.discount.index', compact('discounts', 'categories','search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'category_id' => 'required|unique:category_discounts,category_id',
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount_type' => 'required',
            'discount_amount' => 'required',
            'maximum_amount' => 'required_if:discount_type,percent',
        ],[
            'name.required'=>translate('Name is required'),
            'category_id.required'=>translate('Category select is required'),
            'start_date.required'=>translate('Start date select is required'),
            'expire_date.required'=>translate('Expire date select is required'),
            'category_id.unique'=>translate('Discount on this Category is already exist'),
            'discount_type.required'=>translate('Discount type is required'),
            'discount_amount.required'=>translate('Discount amount is required'),
        ]);

        if ($request->discount_type === 'percent' && $request->discount_amount > 100){
            Toastr::error(translate('Discount amount can not more than 100 percent!'));
            return back();
        }

        $discount = new CategoryDiscount();
        $discount->name = $request->name;
        $discount->category_id = $request->category_id;
        $discount->start_date = $request->start_date;
        $discount->expire_date = $request->expire_date;
        $discount->discount_type = $request->discount_type;
        $discount->discount_amount = $request->discount_amount;
        $discount->maximum_amount = $request->discount_type == 'percent' ? $request->maximum_amount : 0;
        $discount->save();
        Toastr::success(translate('Discount added successfully!'));
        return back();
    }

    public function edit($id)
    {
        $discount = CategoryDiscount::find($id);
        $categories = Category::where(['parent_id'=>0])->orderBy('name')->get();
        return view('admin-views.discount.edit', compact('discount', 'categories'));
    }

    public function status(Request $request)
    {
        $discount = CategoryDiscount::find($request->id);
        $discount->status = $request->status;
        $discount->save();
        Toastr::success(translate('Discount status updated!'));
        return back();
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:255',
            'category_id' => 'required|unique:category_discounts,category_id,' .$id,
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount_type' => 'required',
            'discount_amount' => 'required',
        ],[
            'name.required'=>translate('Name is required'),
            'category_id.required'=>translate('Category select is required'),
            'start_date.required'=>translate('Start date select is required'),
            'expire_date.required'=>translate('Expire date select is required'),
            'category_id.unique'=>translate('Discount on this Category is already exist'),
            'discount_type.required'=>translate('Discount type is required'),
            'discount_amount.required'=>translate('Discount amount is required'),
        ]);

        $discount = CategoryDiscount::find($id);
        $discount->name = $request->name;
        $discount->category_id = $request->category_id;
        $discount->start_date = $request->start_date;
        $discount->expire_date = $request->expire_date;
        $discount->discount_type = $request->discount_type;
        $discount->discount_amount = $request->discount_amount;
        $discount->save();
        Toastr::success(translate('Discount updated successfully!'));
        return redirect()->route('admin.discount.add-new');
    }

    public function delete(Request $request)
    {
        $discount = CategoryDiscount::find($request->id);
        $discount->delete();
        Toastr::success(translate('Discount removed!'));
        return back();
    }
}
