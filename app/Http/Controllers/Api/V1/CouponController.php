<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Coupon;
use App\Model\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    public function list(Request $request)
    {
        try {
            $coupon = Coupon::where('status', 1)
                ->where('start_date', '<=', now()->format('Y-m-d'))
                ->where('expire_date', '>=', now()->format('Y-m-d'))
                ->where(function($query) use ($request) {
                    $query->where('customer_id', $request->user()->id)
                        ->orWhere('customer_id', null);
                })
                ->get();
            return response()->json($coupon, 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function apply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $coupon = Coupon::active()->where(['code' => $request['code']])->first();
            if (isset($coupon)) {

                //default coupon type
                if ($coupon['coupon_type'] == 'default') {
                    $total = Order::where(['user_id' => $request->user()->id, 'coupon_code' => $request['code']])->count();
                    if ($total < $coupon['limit']) {
                        return response()->json($coupon, 200);
                    }else{
                        return response()->json([
                            'errors' => [
                                ['code' => 'coupon', 'message' => translate('coupon limit is over')]
                            ]
                        ], 403);
                    }
                }

                //first order coupon type
                if($coupon['coupon_type'] == 'first_order') {
                    $total = Order::where(['user_id' => $request->user()->id, 'coupon_code' => $request['code'] ])->count();
                    if ($total == 0) {
                        return response()->json($coupon, 200);
                    }else{
                        return response()->json([
                            'errors' => [
                                ['code' => 'coupon', 'message' => translate('This coupon in not valid for you!')]
                            ]
                        ], 403);
                    }
                }

                //free delivery
                if($coupon['coupon_type'] == 'free_delivery') {
                    $total = Order::where(['user_id' => $request->user()->id, 'coupon_code' => $request['code'] ])->count();
                    if ($total < $coupon['limit']) {
                        return response()->json($coupon, 200);
                    }else{
                        return response()->json([
                            'errors' => [
                                ['code' => 'coupon', 'message' => translate('This coupon in not valid for you!')]
                            ]
                        ], 403);
                    }
                }

                //customer wise
                if($coupon['coupon_type'] == 'customer_wise') {

                    $total = Order::where(['user_id' => $request->user()->id, 'coupon_code' => $request['code'] ])->count();

                    if ($coupon['customer_id'] != $request->user()->id){
                        return response()->json([
                            'errors' => [
                                ['code' => 'coupon', 'message' => translate('This coupon in not valid for you!')]
                            ]
                        ], 403);
                    }

                    if ($total < $coupon['limit']) {
                        return response()->json($coupon, 200);
                    }else{
                        return response()->json([
                            'errors' => [
                                ['code' => 'coupon', 'message' => translate('This coupon in not valid for you!')]
                            ]
                        ], 403);
                    }
                }

            } else {
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => 'not found!']
                    ]
                ], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }
}
