<?php

namespace Modules\Shopify\App\Http\Controllers\Middleware\Erply;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Shopify\App\Models\ErplyModel\Product;
use Modules\Shopify\App\Models\ErplyModel\UserOprationLOg;
use Modules\Shopify\App\Models\Source\SourceProduct;

class DeletedProductController extends Controller
{

    public function index(Request $request)
    {
        $limit = $request->get('limit') ?? 100;
        $debug = $request->get('debug') ?? 0;
        $deletedProducts = UserOprationLOg::query()
            ->where([
                'tableName' => 'products',
                'operation' => 'delete',
                'sourcePending' => 1
            ])
            ->orderBy('created_at', 'asc')
            ->take($limit)
            ->get();


        if ($debug == 1) {
            dd($deletedProducts);
        }


        $messages = [];

        if ($deletedProducts) {
            foreach ($deletedProducts as $deletedProduct) {
                $sourceProduct = SourceProduct::where('stockId', $deletedProduct->itemID)->first();
                if (!$sourceProduct) {
                    $messages[$deletedProduct->itemID] = 'Product not found ';
                    $deletedProduct->update([
                        'sourcePending' => 2
                    ]);
                    continue;
                }
                $deleted = $sourceProduct->update([
                    'isDeleted' => 1
                ]);
                if ($deleted) {
                    $messages[$deletedProduct->itemID] = 'Product found to be deleted ';
                }
                if ($debug == 2) {
                    dd($deleted);
                }
                $deletedProduct->update([
                    'sourcePending' => 0
                ]);
            }
            return response()->json([
                'messages' => $messages
            ], 200);
        }
        return response()->json([
            'message' => 'No data found',
        ], 200);
    }

    public function deletedfrommatrix(Request $request)
    {
        $debug = $request->get('debug') ?? 0;
        $limit = $request->get('limit') ?? 100;
        $products = Product::where('erplyDeleted', 1)->where('roadhouseStatus', '!=', 9)->take($limit)->get();
        if ($debug == 1) {
            dd($products);
        }
        if ($products->isEmpty()) {
            return response()->json(['message' => 'No data found'], 404);
        }
        $msg = [];
        foreach ($products as $product) {
            $sourceProduct = SourceProduct::where('stockId', $product->productID)->first();
            if ($debug == 2) {
                dd($sourceProduct);
            }
            if ($sourceProduct) {
                $data =  $sourceProduct->update([
                    'isDeleted' => 1
                ]);
                if ($data) {
                    $msg[$product->productID] = 'Product found to be deleted ';
                } else {
                    $msg[$product->productID] = 'Source Product not found ';
                }
            }
            $product->update([
                'roadhouseStatus' => 9
            ]);
        }

        return response()->json([
            'message' => 'success',
            'data' => $msg
        ], 200);
    }
}
