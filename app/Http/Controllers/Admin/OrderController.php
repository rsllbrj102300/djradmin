<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\BusinessSetting;
use App\Model\DeliveryMan;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use function App\CentralLogics\translate;

class OrderController extends Controller
{
    public function list(Request $request, $status)
    {
        $query_param = [];
        $search = $request['search'];

        $branches = Branch::all();
        $branch_id = $request['branch_id'];
        $start_date = $request['start_date'];
        $end_date = $request['end_date'];

        Order::where(['checked' => 0])->update(['checked' => 1]);

        if ($status != 'all') {
            $query = Order::with(['customer', 'branch'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    //return $query->whereBetween('created_at', [$start_date, $end_date]);
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->where(['order_status' => $status]);
            $query_param = ['branch_id' => $branch_id, 'start_date' => $start_date,'end_date' => $end_date ];

        } else {
            $query = Order::with(['customer', 'branch'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    //return $query->whereBetween('created_at', [$start_date, $end_date]);
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                });
            $query_param = ['branch_id' => $branch_id, 'start_date' => $start_date,'end_date' => $end_date ];
        }

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('payment_status', 'like', "{$value}%");
                }
            });
            $query_param = ['search' => $request['search'], 'branch_id' => $request['branch_id'], 'start_date' => $request['start_date'],'end_date' => $request['end_date'] ];
        }

        $orders = $query->notPos()->orderBy('id', 'desc')->paginate(Helpers::getPagination())->appends($query_param);

        $count_data = [
            'pending' => Order::notPos()->where(['order_status'=>'pending'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),

            'confirmed' => Order::notPos()->where(['order_status'=>'confirmed'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),

            'processing' => Order::notPos()->where(['order_status'=>'processing'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),

            'out_for_delivery' => Order::notPos()->where(['order_status'=>'out_for_delivery'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),

            'delivered' => Order::notPos()->where(['order_status'=>'delivered'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),

            'canceled' => Order::notPos()->where(['order_status'=>'canceled'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),

            'returned' => Order::notPos()->where(['order_status'=>'returned'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),

            'failed' => Order::notPos()->where(['order_status'=>'failed'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->count(),
        ];

        return view('admin-views.order.list', compact('orders', 'status', 'search', 'branches', 'branch_id', 'start_date', 'end_date', 'count_data'));
    }

    public function details($id)
    {
        $delivery_man = DeliveryMan::where(['is_active'=>1])->get();
        $order = Order::with('details')->where(['id' => $id])->first();

        if (isset($order)) {
            return view('admin-views.order.order-view', compact('order', 'delivery_man'));
        } else {
            Toastr::info(translate('No more orders!'));
            return back();
        }
    }

    public function search(Request $request)
    {

        $key = explode(' ', $request['search']);
        $orders = Order::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('order_status', 'like', "%{$value}%")
                    ->orWhere('transaction_reference', 'like', "%{$value}%");
            }
        })->latest()->paginate(2);

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders'))->render(),
        ]);
    }

    public function date_search(Request $request)
    {
        $dateData = ($request['dateData']);

        $orders = Order::where(['delivery_date' => $dateData])->latest()->paginate(10);
        // $timeSlots = $orders->pluck('time_slot_id')->unique()->toArray();
        // if ($timeSlots) {

        //     $timeSlots = TimeSlot::whereIn('id', $timeSlots)->get();
        // } else {
        //     $timeSlots = TimeSlot::orderBy('id')->get();

        // }
        // dd($orders);

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders'))->render(),
            // 'timeSlot' => $timeSlots
        ]);

    }

    public function time_search(Request $request)
    {

        $orders = Order::where(['time_slot_id' => $request['timeData']])->where(['delivery_date' => $request['dateData']])->get();
        // dd($orders)->toArray();

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders'))->render(),
        ]);

    }

    public function status(Request $request)
    {
        $order = Order::find($request->id);

        if (in_array($order->order_status, ['delivered', 'failed'])) {
            Toastr::warning(translate('you_can_not_change_the_status_of_a_completed_order'));
            return back();
        }

        if ($request->order_status == 'delivered' && $order['transaction_reference'] == null && !in_array($order['payment_method'],['cash_on_delivery','wallet'])) {
            Toastr::warning(translate('add_your_payment_reference_first'));
            return back();
        }

        if ( $request->order_status == 'out_for_delivery' && $order['delivery_man_id'] == null && $order['order_type'] != 'self_pickup') {
            Toastr::warning(translate('Please assign delivery man first!'));
            return back();
        }

        if ($request->order_status == 'returned' || $request->order_status == 'failed' || $request->order_status == 'canceled') {
            foreach ($order->details as $detail) {

                if ($detail['is_stock_decreased'] == 1) {
                    $product = Product::find($detail['product_id']);

                    if($product != null){
                        $type = json_decode($detail['variation'])[0]->type;
                        $var_store = [];
                        foreach (json_decode($product['variations'], true) as $var) {
                            if ($type == $var['type']) {
                                $var['stock'] += $detail['quantity'];
                            }
                            array_push($var_store, $var);
                        }
                        Product::where(['id' => $product['id']])->update([
                            'variations' => json_encode($var_store),
                            'total_stock' => $product['total_stock'] + $detail['quantity'],
                        ]);
                        OrderDetail::where(['id' => $detail['id']])->update([
                            'is_stock_decreased' => 0,
                        ]);
                    }
                }else{
                    Toastr::warning(translate('Product_deleted'));
                }

            }
        } else {
            foreach ($order->details as $detail) {
                if ($detail['is_stock_decreased'] == 0) {
                    $product = Product::find($detail['product_id']);
                    if($product != null){
                        //check stock
                        foreach ($order->details as $c) {
                            $product = Product::find($c['product_id']);
                            $type = json_decode($c['variation'])[0]->type;
                            foreach (json_decode($product['variations'], true) as $var) {
                                if ($type == $var['type'] && $var['stock'] < $c['quantity']) {
                                    Toastr::error(translate('Stock is insufficient!'));
                                    return back();
                                }
                            }
                        }

                        $type = json_decode($detail['variation'])[0]->type;
                        $var_store = [];
                        foreach (json_decode($product['variations'], true) as $var) {
                            if ($type == $var['type']) {
                                $var['stock'] -= $detail['quantity'];
                            }
                            array_push($var_store, $var);
                        }
                        Product::where(['id' => $product['id']])->update([
                            'variations' => json_encode($var_store),
                            'total_stock' => $product['total_stock'] - $detail['quantity'],
                        ]);
                        OrderDetail::where(['id' => $detail['id']])->update([
                            'is_stock_decreased' => 1,
                        ]);
                    }
                    else{
                        Toastr::warning(translate('Product_deleted'));
                    }

                }
            }
        }

        if ($request->order_status == 'delivered') {
            if($order->user_id) {
                CustomerLogic::create_loyalty_point_transaction($order->user_id, $order->id, $order->order_amount, 'order_place');
            }

            $user = User::find($order->user_id);
            $is_first_order = Order::where('user_id', $user->id)->count('id');
            $referred_by_user = User::find($user->referred_by);

            if ($is_first_order < 2 && isset($user->referred_by) && isset($referred_by_user)){
                if(BusinessSetting::where('key','ref_earning_status')->first()->value == 1) {
                    CustomerLogic::referral_earning_wallet_transaction($order->user_id, 'referral_order_place', $referred_by_user->id);
                }
            }
        }

        $order->order_status = $request->order_status;
        $order->save();
        $fcm_token = isset($order->customer) ? $order->customer->cm_firebase_token : null;
        $value = Helpers::order_status_update_message($request->order_status);
        try {
            if ($value) {
                $data = [
                    'title' => translate('Order'),
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                    'type' => 'order'
                ];
                Helpers::send_push_notif_to_device($fcm_token, $data);
            }
        } catch (\Exception $e) {
            Toastr::warning(\App\CentralLogics\translate('Push notification failed for Customer!'));
        }

        //delivery man notification
        if ($request->order_status == 'processing' && $order->delivery_man != null) {
            $fcm_token = $order->delivery_man->fcm_token;
            $value = \App\CentralLogics\translate('One of your order is in processing');
            try {
                if ($value) {
                    $data = [
                        'title' => translate('Order'),
                        'description' => $value,
                        'order_id' => $order['id'],
                        'image' => '',
                        'type' => 'order'
                    ];
                    Helpers::send_push_notif_to_device($fcm_token, $data);
                }
            } catch (\Exception $e) {
                Toastr::warning(\App\CentralLogics\translate('Push notification failed for DeliveryMan!'));
            }
        }

        Toastr::success(translate('Order status updated!'));
        return back();
    }

    public function add_delivery_man($order_id, $delivery_man_id)
    {
        if ($delivery_man_id == 0) {
            return response()->json([], 401);
        }

        $order = Order::find($order_id);

        if ($order->order_status == 'delivered' || $order->order_status == 'returned' || $order->order_status == 'failed' || $order->order_status == 'canceled') {
            return response()->json(['status' => false], 200);
        }

        $order->delivery_man_id = $delivery_man_id;
        $order->save();

        $fcm_token = $order->delivery_man->fcm_token;
        $customer_fcm_token = $order->customer->cm_firebase_token;
        $value = Helpers::order_status_update_message('del_assign');
        try {
            if ($value) {
                $data = [
                    'title' => translate('Order'),
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                    'type' => 'order'
                ];
                Helpers::send_push_notif_to_device($fcm_token, $data);
                $cs_notify_message = Helpers::order_status_update_message('customer_notify_message');
                if($cs_notify_message) {
                    $data['description'] = $cs_notify_message;
                    Helpers::send_push_notif_to_device($customer_fcm_token, $data);
                }
            }
        } catch (\Exception $e) {
            Toastr::warning(\App\CentralLogics\translate('Push notification failed for DeliveryMan!'));
        }

        Toastr::success('Deliveryman successfully assigned/changed!');
        return response()->json(['status' => true], 200);
    }

    public function payment_status(Request $request)
    {
        $order = Order::find($request->id);
        if ($request->payment_status == 'paid' && $order['transaction_reference'] == null && $order['payment_method'] != 'cash_on_delivery') {
            Toastr::warning(translate('Add your payment reference code first!'));
            return back();
        }
        $order->payment_status = $request->payment_status;
        $order->save();
        Toastr::success(translate('Payment status updated!'));
        return back();
    }

    public function update_shipping(Request $request, $id)
    {
        $request->validate([
            'contact_person_name' => 'required',
            'address_type' => 'required',
            'contact_person_number' => 'required',
            'address' => 'required',
        ]);

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => $request->address_type,
            'address' => $request->address,
            'longitude' => $request->longitude,
            'latitude' => $request->latitude,
            'road' => $request->road,
            'house' => $request->house,
            'floor' => $request->floor,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('customer_addresses')->where('id', $id)->update($address);
        Toastr::success(translate('Delivery Information updated!'));
        return back();
    }

    public function update_time_slot(Request $request)
    {
        if ($request->ajax()) {
            $order = Order::find($request->id);
            $order->time_slot_id = $request->timeSlot;
            $order->save();
            $data = $request->timeSlot;

            return response()->json($data);
        }
    }

    public function update_deliveryDate(Request $request)
    {
        if ($request->ajax()) {
            $order = Order::find($request->id);
            $order->delivery_date = $request->deliveryDate;
           // dd($order);
            $order->save();
            $data = $request->deliveryDate;
            return response()->json($data);
        }
    }

    public function generate_invoice($id)
    {
        $order = Order::where('id', $id)->first();
        $footer_text = BusinessSetting::where(['key' => 'footer_text'])->first();
        return view('admin-views.order.invoice', compact('order', 'footer_text'));
    }

    public function add_payment_ref_code(Request $request, $id)
    {
        Order::where(['id' => $id])->update([
            'transaction_reference' => $request['transaction_reference'],
        ]);

        Toastr::success(translate('Payment reference code is added!'));
        return back();
    }

    public function branch_filter($id)
    {
        session()->put('branch_filter', $id);
        return back();
    }

    public function export_orders(Request $request, $status)
    {
        $query_param = [];
        $search = $request['search'];
        $branch_id = $request['branch_id'];
        $start_date = $request['start_date'];
        $end_date = $request['end_date'];

        if ($status != 'all') {
            $query = Order::with(['customer', 'branch'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                })->where(['order_status' => $status]);
        } else {
            $query = Order::with(['customer', 'branch'])
                ->when((!is_null($branch_id) && $branch_id != 'all'), function ($query) use ($branch_id) {
                    return $query->where('branch_id', $branch_id);
                })->when((!is_null($start_date) && !is_null($end_date)), function ($query) use ($start_date, $end_date) {
                    return $query->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date);
                });
        }

        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $query = $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('order_status', 'like', "%{$value}%")
                        ->orWhere('payment_status', 'like', "%{$value}%");
                }
            });
            $query_param = ['search' => $request['search']];
        }

        //$orders = $query->notPos()->orderBy('id', 'desc')->paginate(Helpers::getPagination())->appends($query_param);
        $orders = $query->notPos()->orderBy('id', 'desc')->get();

        $storage = [];

        foreach($orders as $order){
            $branch = $order->branch ? $order->branch->name : '';
            $customer = $order->customer ? $order->customer->f_name .' '. $order->customer->l_name : 'Customer Deleted';
            //$delivery_address = $order->delivery_address ? $order->delivery_address['address'] : '';
            $delivery_man = $order->delivery_man ? $order->delivery_man->f_name .' '. $order->delivery_man->l_name : '';
            $timeslot = $order->time_slot ? $order->time_slot->start_time .' - '. $order->time_slot->end_time : '';

            $storage[] = [
                'order_id' => $order['id'],
                'customer' => $customer,
                'order_amount' => $order['order_amount'],
                'coupon_discount_amount' => $order['coupon_discount_amount'],
                'payment_status' => $order['payment_status'],
                'order_status' => $order['order_status'],
                'total_tax_amount'=>$order['total_tax_amount'],
                'payment_method' => $order['payment_method'],
                'transaction_reference' => $order['transaction_reference'],
               // 'delivery_address' => $delivery_address,
                'delivery_man' => $delivery_man,
                'delivery_charge' => $order['delivery_charge'],
                'coupon_code' => $order['coupon_code'],
                'order_type' => $order['order_type'],
                'branch'=>  $branch,
                'time_slot_id' => $timeslot,
                'date' => $order['date'],
                'delivery_date' => $order['delivery_date'],
                'extra_discount' => $order['extra_discount'],
            ];
        }
        //return $storage;
        return (new FastExcel($storage))->download('orders.xlsx');
    }
}
