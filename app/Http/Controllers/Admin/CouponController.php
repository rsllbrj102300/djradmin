<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Coupon;
use App\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function add_new(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if($request->has('search'))
        {
            $key = explode(' ', $request['search']);
            $coupons = Coupon::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('title', 'like', "%{$value}%")
                        ->orWhere('code', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }else{
            $coupons = new Coupon;
        }
        $customers = User::where('is_block', 0)->get();
        $coupons = $coupons->withCount('order')->latest()->paginate(Helpers::getPagination())->appends($query_param);

        return view('admin-views.coupon.index', compact('coupons','search', 'customers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|max:15|unique:coupons',
            'title' => 'required|max:100',
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount' => 'required_if:coupon_type,default,first_order,customer_wise',
            'limit' => 'required_if:coupon_type,default,customer_wise,free_delivery',
        ],[
            'expire_date.required' => translate('Expired date is required')
        ]);


        if ($request->coupon_type == 'free_delivery'){
            $discount = 0;
        }else{
            $discount = $request->discount_type == 'amount' ? $request->discount : $request['discount'];
        }

        $data = [
            'title' => $request->title,
            'code' => $request->code,
            'limit' => $request->coupon_type!='first_order' ? $request->limit : null,
            'coupon_type' => $request->coupon_type,
            'start_date' => $request->start_date,
            'expire_date' => $request->expire_date,
            'min_purchase' => $request->min_purchase != null ? $request->min_purchase : 0,
            'max_discount' => $request->discount_type != 'amount' ? $request->max_discount : 0,
            'discount' => $discount,
            'discount_type' => $request->discount_type,
            'customer_id' => $request->customer_id,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('coupons')->insert([
            $data
        ]);

        Toastr::success(translate('Coupon added successfully!'));
        return back();
    }

    public function edit($id)
    {
        $coupon = Coupon::where(['id' => $id])->first();
        $customers = User::where('is_block', 0)->get();
        return view('admin-views.coupon.edit', compact('coupon','customers'));
    }

    public function update(Request $request, $id)
    {
        //dd($request->all());
        $request->validate([
            'code' => 'required|max:15|unique:coupons,code,'.$id.',id',
            'title' => 'required|max:100',
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount' => 'required_if:coupon_type,default,first_order,customer_wise',
            'limit' => 'required_if:coupon_type,default,customer_wise,free_delivery',
        ],[
            'code.required' => translate('Code is required'),
            'code.unique' => translate('Code must be unique'),
        ]);

        if ($request->coupon_type == 'free_delivery'){
            $discount = 0;
        }else{
            $discount = $request->discount_type == 'amount' ? $request->discount : $request['discount'];
        }

        $coupon= Coupon::find($id);
        $coupon->title = $request->title;
        $coupon->code = $request->code;
        $coupon->limit = $request->coupon_type!='first_order' ? $request->limit : null;
        $coupon->coupon_type = $request->coupon_type;
        $coupon->start_date = $request->start_date;
        $coupon->expire_date = $request->expire_date;
        $coupon->min_purchase = $request->min_purchase != null ? $request->min_purchase : 0;
        $coupon->max_discount = $request->discount_type != 'amount' ? $request->max_discount : 0;
        $coupon->discount = $discount;
        $coupon->discount_type = $request->discount_type;
        $coupon->customer_id = $request->customer_id;

        $coupon->save();

        Toastr::success(translate('Coupon updated successfully!'));
        return back();
    }

    public function status(Request $request)
    {
        $coupon = Coupon::find($request->id);
        $coupon->status = $request->status;
        $coupon->save();
        Toastr::success(translate('Coupon status updated!'));
        return back();
    }

    public function delete(Request $request)
    {
        $coupon = Coupon::find($request->id);
        $coupon->delete();
        Toastr::success(translate('Coupon removed!'));
        return back();
    }

    public function quick_view_details(Request $request)
    {
        $coupon = Coupon::find($request->id);

        return response()->json([
            'view' => view('admin-views.coupon.details-quick-view', compact('coupon'))->render(),
        ]);
    }

}
