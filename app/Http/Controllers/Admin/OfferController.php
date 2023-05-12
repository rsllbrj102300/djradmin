<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\FlashDeal;
use App\Model\FlashDealProduct;
use App\Model\Product;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OfferController extends Controller
{
    public function flash_index(Request $request)
    {
        $query_param = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key = explode(' ', $request['search']);
            $flash_deal = FlashDeal::withCount('products')
                ->where('deal_type', 'flash_deal')
                ->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->Where('title', 'like', "%{$value}%");
                    }
                });
            $query_param = ['search' => $request['search']];
        } else {
            $flash_deal = FlashDeal::withCount('products')->where('deal_type', 'flash_deal');
        }
        $flash_deals = $flash_deal->latest()->paginate(Helpers::getPagination())->appends($query_param);
        //return($flash_deals);
        return view('admin-views.offer.flash-deal-index', compact('flash_deals', 'search'));
    }

    public function flash_store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:255',
            'start_date' => 'required',
            'end_date' => 'required',
            'image' => 'required',
        ],[
            'title.required'=>translate('Title is required'),
        ]);

        if (!empty($request->file('image'))) {
            $image_name = Helpers::upload('offer/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $flash_deal = new FlashDeal();
        $flash_deal->title = $request->title;
        $flash_deal->start_date = $request->start_date;
        $flash_deal->end_date = $request->end_date;
        $flash_deal->deal_type = 'flash_deal';
        $flash_deal->status = 0;
        $flash_deal->featured = 0;
        $flash_deal->image = $image_name;
        $flash_deal->save();
        Toastr::success(translate('Flash deal added successfully!'));
        return back();
    }

    public function status(Request $request)
    {
        FlashDeal::where(['status' => 1])->update(['status' => 0]);
        $flash_deal = FlashDeal::find($request->id);
        $flash_deal->status = $request->status;
        $flash_deal->save();
        Toastr::success(translate('Flash deal status updated!'));
        return back();
    }

    public function delete(Request $request)
    {
        $flash_deal = FlashDeal::find($request->id);
        if (Storage::disk('public')->exists('offer/' . $flash_deal['image'])) {
            Storage::disk('public')->delete('offer/' . $flash_deal['image']);
        }
        $flash_deal_product_ids = FlashDealProduct::where(['flash_deal_id' => $request->id])->pluck('product_id');
        $flash_deal->delete();
        FlashDealProduct::whereIn('id', $flash_deal_product_ids)->delete();
        Toastr::success(translate('Flash deal removed!'));
        return back();
    }

    public function flash_edit($flash_deal_id)
    {
        $flash_deal = FlashDeal::find($flash_deal_id);
        return view('admin-views.offer.edit-flash-deal', compact('flash_deal'));
    }

    public function flash_update(Request $request, $flash_deal_id)
    {
        $request->validate([
            'title' => 'required|max:255',
            'start_date' => 'required',
            'end_date' => 'required',
        ],[
            'title.required'=>translate('Title is required'),
        ]);

        $flash_deal = FlashDeal::find($flash_deal_id);
        $flash_deal->title = $request->title;
        $flash_deal->start_date = $request->start_date;
        $flash_deal->end_date = $request->end_date;
        $flash_deal->image = $request->has('image') ? Helpers::update('offer/', $flash_deal->image, 'png', $request->file('image')) : $flash_deal->image;
        ;
        $flash_deal->save();
        Toastr::success(translate('Flash deal updated successfully!'));
        return redirect()->route('admin.offer.flash.index');
    }

    public function flash_add_product($flash_deal_id)
    {
        $flash_deal = FlashDeal::where('id', $flash_deal_id)->first();
        $flash_deal_product_ids = FlashDealProduct::where('flash_deal_id', $flash_deal_id)->pluck('product_id');
        $flash_deal_products = Product::whereIn('id', $flash_deal_product_ids)->paginate(Helpers::getPagination());
        $products = Product::active()->orderBy('name', 'asc')->get();

        // dd($flash_deal);
        return view('admin-views.offer.add-product-index', compact('flash_deal', 'flash_deal_products', 'products'));
    }

    public function flash_product_store(Request $request, $flash_deal_id)
    {
        $this->validate($request, [
            'product_id' => 'required'
        ]);
        $flash_deal_products = FlashDealProduct::where(['flash_deal_id' => $flash_deal_id, 'product_id' => $request['product_id']])->first();

        if(!isset($flash_deal_products))
        {
            DB::table('flash_deal_products')->insertOrIgnore([
                'product_id' => $request['product_id'],
                'flash_deal_id' => $flash_deal_id,
                'discount' => $request['discount'],
                'discount_type' => $request['discount_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Toastr::success('Product added successfully!');
        }else{
            Toastr::info('Product already added!');
        }
        return back();
    }

    public function delete_flash_product(Request $request)
    {
        FlashDealProduct::where(['product_id' => $request->id, 'flash_deal_id' => $request->flash_deal_id])->delete();
        return response()->json();
    }
}
