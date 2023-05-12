<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\FlashDeal;
use App\Model\FlashDealProduct;
use App\Model\Product;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function get_flash_deal(Request $request)
    {
        try {
            $flash_deals = FlashDeal::active()
                ->where('deal_type','flash_deal')
                ->first();

            return response()->json($flash_deals, 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function get_flash_deal_products(Request $request, $flash_deal_id)
    {
        $p_ids = FlashDealProduct::with(['product'])
            ->whereHas('product',function($q){
                $q->active();
            })
            ->where(['flash_deal_id' => $flash_deal_id])
            ->pluck('product_id')
            ->toArray();

        //dd($p_ids);

        if (count($p_ids) > 0) {
            $paginator = Product::with(['rating'])
               ->whereIn('id', $p_ids)
               ->paginate($request['limit'], ['*'], 'page', $request['offset']);

            $products = [
                'total_size' => $paginator->total(),
                'limit' => $request['limit'],
                'offset' => $request['offset'],
                'products' => $paginator->items()
            ];

            $products['products'] = Helpers::product_data_formatting($products['products'], true);
            return response()->json($products, 200);
        }

        return response()->json([], 200);
    }
}
