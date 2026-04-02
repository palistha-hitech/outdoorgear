<?php
namespace Modules\Shopify\App\Http\Controllers\ReadShopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Myob\App\Http\Controllers\Myob\Services\MyobWriteService;
use Modules\Myob\App\Models\SourceVariant;
use Modules\Shopify\App\Models\ReadShopify\ShopifyImage;
use Modules\Shopify\App\Models\ReadShopify\ShopifyProduct;
use Modules\Shopify\App\Models\ReadShopify\ShopifyVariantsProduct;
use Modules\Shopify\App\Models\ShopifyCursor;
use Modules\Shopify\App\Traits\ShopifyTrait;

class ShopifyProductsController extends Controller
{
    use ShopifyTrait;

    protected $live = 1;

    protected $clientCode;

    protected $myobWriteService;

    public function __construct(MyobWriteService $myobWriteService)
    {
        $this->live             = 1;
        $this->clientCode       = 'default';
        $this->myobWriteService = $myobWriteService;
    }

    public function getProducts(Request $request)
    {
        // $debug = 0;
        $productId = $request->productId;
        // if ($productId) {
        //     $query = $this->getSingleproductsQuery($productId);
        // } else {
        $query = $this->getMatrixProductQueryByLastmodified($this->clientCode, $this->live);

        // }

        $debug = $request->debug ?? 0;
        // if (isset($request->debug)) {
        //     $debug = $request->debug;
        // }

        if ($debug == 1) {
            dd($query);
        }
        $res = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
        if ($debug == 2) {
            dd($res);
        }
        if (! empty($res->data->products->edges)) {
            DB::beginTransaction();
            try {
                $lmd = '';
                foreach ($res->data->products->edges as $productEdge) {
                    $productNode = $productEdge->node;
                    $cursor      = $productEdge->cursor;

                    $lmd = $productNode->updatedAt;
                    Log::info('products: ' . json_encode($productNode));
                    $shopifyproductStringId = $productNode->id;
                    $shopifyproductId       = last(explode('/', $productNode->id));
                    $tags                   = implode(',', $productNode->tags);
                    $productType            = $productNode->hasOnlyDefaultVariant ? 'simple' : 'matrix';
                    if ($debug == 3) {
                        dd([
                            'shopify_products_string_id' => $shopifyproductStringId,
                            'title'                      => $productNode->title,
                            'totalInventory'             => $productNode->totalInventory,
                            'totalVariants'              => $productNode->totalVariants,
                            'status'                     => @$productNode->status,
                            'vendor'                     => @$productNode->vendor,
                            'productType'                => @$productType,
                            'description'                => @$productNode->description,
                            'tags'                       => $tags,
                            'handle'                     => @$productNode->handle,

                        ]);
                    }
                    $sourceproduct = ShopifyProduct::updateOrCreate(
                        ['shopify_products_id' => strval($shopifyproductId)],
                        [
                            'shopify_products_string_id' => $shopifyproductStringId,
                            'title'                      => $productNode->title,
                            'totalInventory'             => $productNode->totalInventory,
                            'totalVariants'              => $productNode->totalVariants,
                            'status'                     => @$productNode->status,
                            'vendor'                     => @$productNode->vendor,
                            'productType'                => @$productType,
                            'description'                => @$productNode->description,
                            'tags'                       => $tags,
                            'handle'                     => @$productNode->handle,
                            'Shopify_added_date'         => $productNode->createdAt,
                            'Shopify_updated_date'       => $productNode->updatedAt,
                        ]
                    );
                    foreach ($productNode->images->edges as $imageEdge) {
                        $imageUrl = $imageEdge->node->url;
                        ShopifyImage::updateOrCreate(
                            ['product_string_id' => $shopifyproductStringId],
                            [
                                'shopifyMediaId' => $imageEdge->node->id ?? null,
                                'url'            => $imageUrl,
                                'productId'      => $sourceproduct->id,
                            ]
                        );
                    }

                    // if (@$res->data->products->pageInfo->endCursor) {

                    //     $lastCursor = @$res->data->products->pageInfo->endCursor;

                    //     ShopifyCursor::updateOrcreate(
                    //         [
                    //             'clientCode' => $this->clientCode,
                    //             'cursorName' => 'matrixProductV2',
                    //             'isLive' => $this->live,
                    //         ],
                    //         [
                    //             'clientCode' => $this->clientCode,
                    //             'cursorName' => 'matrixProductV2',
                    //             'cursor' => $cursor,
                    //             'isLive' => $this->live,
                    //         ]
                    //     );
                    //     Log::info("Extracted cursor: {$lastCursor}");
                    // }
                }
                if (! empty($lmd)) {
                    ShopifyCursor::updateOrcreate(
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'matrixProductV2',
                            'isLive'     => $this->live,
                        ],
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'matrixProductV2',
                            'cursor'     => $lmd,
                            'isLive'     => $this->live,
                        ]
                    );
                }
                DB::commit();
                dump(@$res);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error processing products: ' . $e->getMessage());

                throw $e;
            }
        } else {
            return response('No products Found to Sync');
        }
    }

    public function getVariantsProducts(Request $request)
    {
        $debug     = 0;
        $productId = $request->productId;
        // if ($productId) {
        //     $query = $this->getSingleproductsQuery($productId);
        // } else {
        $query = $this->getVariationProductQuery($this->clientCode, $this->live);
        // }
        if (isset($request->debug)) {
            $debug = $request->debug;
        }
        if ($debug == 1) {
            dd($query);
        }
        $res = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);

        if ($debug == 2) {
            dd($res);
        }
        if (! empty($res->data->productVariants->edges)) {
            DB::beginTransaction();
            try {
                $lmd = '';
                foreach ($res->data->productVariants->edges as $productEdge) {
                    $productNode      = $productEdge->node;
                    $inventoryItemGid = $productNode->inventoryItem->id ?? null;
                    $inventoryItemId  = $inventoryItemGid
                        ? last(explode('/', $inventoryItemGid))
                        : null;

                    // dd($productNode->product->status);
                    if (@$productNode->product->status == 'ACTIVE') {
                        continue; // skip ACTIVE products
                    }
                    $cursor = $productEdge->cursor;

                    Log::info('products: ' . json_encode($productNode));
                    $shopifyproductStringId = $productNode->id;
                    $shopifyproductId       = last(explode('/', $productNode->id));
                    $weight                 = $productNode->inventoryItem->measurement->weight->value ?? null;
                    $weightUnit             = $productNode->inventoryItem->measurement->weight->unit ?? null;
                    if ($debug == 3) {
                        dd([
                            'shopify_products_string_id' => $shopifyproductStringId,
                            'title'                      => $productNode->title,
                            'price'                      => $productNode->price,
                            'totalInventory'             => $productNode->inventoryQuantity,
                            'displayName'                => $productNode->displayName,
                            'weight'                     => $weight,
                            'weightUnit'                 => $weightUnit,
                            'sku'                        => $productNode->sku,
                            'barcode'                    => $productNode->barcode,
                            'shiopifyVariantId'          => $shopifyproductStringId,
                            'shopify_products_id'        => $productNode->product->id,
                            'status'                     => @$productNode->product->status,
                            'color'                      => @$productNode->selectedOptions[0]->value,
                            'size'                       => @$productNode->selectedOptions[1]->value,
                        ]);
                    }
                    $sourceproduct = ShopifyVariantsProduct::updateOrCreate(
                        ['shopify_products_string_id' => $shopifyproductStringId],
                        [
                            'shopify_products_string_id' => $shopifyproductStringId,
                            'title'                      => $productNode->title,
                            'price'                      => $productNode->price,
                            'totalInventory'             => $productNode->inventoryQuantity,
                            'displayName'                => $productNode->displayName,
                            'inventoryitemId'            => $inventoryItemId,
                            // 'weight'                     => $weight,
                            // 'weightUnit'                 => $weightUnit,
                            'sku'                        => $productNode->sku,
                            'barcode'                    => $productNode->barcode,
                            'shiopifyVariantId'          => $shopifyproductStringId,
                            'shopify_products_id'        => $productNode->product->id,
                            'status'                     => @$productNode->product->status,
                            'color'                      => @$productNode->selectedOptions[0]->value,
                            'size'                       => @$productNode->selectedOptions[1]->value,
                        ]
                    );

                    if (@$res->data->productVariants->pageInfo->endCursor) {

                        $lastCursor = @$res->data->productVariants->pageInfo->endCursor;

                        ShopifyCursor::updateOrcreate(
                            [
                                'clientCode' => $this->clientCode,
                                'cursorName' => 'variationProduct',
                                'isLive'     => $this->live,
                            ],
                            [
                                'clientCode' => $this->clientCode,
                                'cursorName' => 'variationProduct',
                                'cursor'     => $cursor,
                                'isLive'     => $this->live,
                            ]
                        );
                        Log::info("Extracted cursor: {$lastCursor}");
                        DB::commit();
                    }
                }
                dump($res);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error processing products: ' . $e->getMessage());

                throw $e;
            }
        } else {
            return response('No products Found to Sync');
        }
    }

    public function handleProductwebHooks(Request $request)
    {
        $data           = $request->all();
        $productDetails = $data;
        $product        = ShopifyProduct::updateOrCreate(
            ['shopify_products_string_id' => $data['admin_graphql_api_id']],
            [
                'title'                => $data['title'],
                'status'               => $data['status'],
                'vendor'               => $data['vendor'],
                'description'          => $data['body_html'],
                'tags'                 => $data['tags'],
                'handle'               => $data['handle'],
                'Shopify_added_date'   => $data['created_at'] ?? now()->toDateTimeString(),
                'Shopify_updated_date' => $data['updated_at'] ?? now()->toDateTimeString(),
            ]
        );

        if (isset($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $variant) {
                ShopifyVariantsProduct::updateOrCreate(
                    ['shiopifyVariantId' => $variant['admin_graphql_api_id']],
                    [
                        'shiopifyVariantId'   => $variant['admin_graphql_api_id'],
                        'shopify_products_id' => $product['id'],
                        'title'               => $variant['title'],
                        'price'               => $variant['price'],
                        'inventory_quantity'  => $variant['inventory_quantity'] ?? 0,
                        'sku'                 => $variant['sku'],
                        'barcode'             => $variant['barcode'],
                        'status'              => $variant['status'] ?? 'active',
                        'color'               => $variant['option1'] ?? null,
                        'size'                => $variant['option2'] ?? null,
                        'weight'              => $variant['grams'],
                        'weight_unit'         => $variant['weight_unit'],
                    ]
                );
            }
        }
        Log::info("Processed Shopify product and variants: " . json_encode($product->with('variants')->get(), JSON_PRETTY_PRINT));
        return response()->json(['status' => 'success', 'message' => 'Product and variants processed successfully']);
    }

    public function handleInventoryUpdatewebHooks(Request $request)
    {
        try {

            // $data = $request->all();

            $inventoryItemId = $data['inventory_item_id'] ?? '46416401203254';

            $stock = $data['available'] ?? null;

            $inventoryItemGid = "gid://shopify/InventoryItem/{$inventoryItemId}";

            // Validate payload
            // if (!$inventoryItemId || is_null($stock)) {
            //     Log::error('Invalid webhook payload', $data);
            //     return response()->json(['error' => 'Invalid payload'], 400);
            // }
            DB::beginTransaction();
            $updatedShopifyVariantsProduct = ShopifyVariantsProduct::where('inventoryItemId', $inventoryItemId)
                ->update([
                    'totalInventory' => $stock,
                    'updated_at'     => now(),
                ]);

            $updatedSourceVariant = SourceVariant::where('inventoryItemId', $inventoryItemGid)
                ->update([
                    'quantityOnHand'        => $stock,
                    'updated_at'            => now(),
                    'myobsohpendingprocess' => 1,
                ]);
            DB::commit();
            if ($updatedShopifyVariantsProduct || $updatedSourceVariant) {

                Log::info("Stock updated for inventoryItemId: {$inventoryItemId}, New SOH: {$stock}");

                // Trigger MYOB SOH sync
                $variantProduct = SourceVariant::where('inventoryItemId', $inventoryItemGid)->first();
                $invoiceNumber  = $variantProduct->sku;

                $this->syncSOHToMyob($invoiceNumber, $stock);

            } else {
                Log::warning("No matching record found for inventoryItemId: {$inventoryItemId}");
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Webhook processing failed: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }
    /**
     * Sync SOH to MYOB after Shopify inventory update
     */
    private function syncSOHToMyob($invoiceNumber, $stock)
    {

        try {
            // Get the source variant with MYOB data
            $sourceVariant = SourceVariant::where('sku', $invoiceNumber)

                ->first();

            $myobQty       = $sourceVariant->quantityOnHand ?? 0;
            $adjustmentQty = $stock - $myobQty;
            $payload       = [
                "Date"  => now()->format('Y-m-d\TH:i:s'),
                "Memo"  => "Stock adjustment from Shopify sync",

                "Lines" => [
                    [
                        "Item"     => [
                            "UID" => $sourceVariant->variantId, // MYOB Item UID
                        ],
                        "Account"  => [
                            "UID" => "cc424eb1-8183-4be8-8597-dad593f18c5e",
                        ],
                        "Quantity" => 20,
                        "UnitCost" => 20,
                        "Location" => [
                            "UID" => '08aca640-d292-4f75-9665-4078b8481065',
                        ],
                    ],
                ],
            ];

            // Call MYOB API to update SOH
            $myobService = $this->myobWriteService;

            // Get MYOB credentials from the product's client
            $clientCode = $sourceVariant->sourceProduct->clientCode ?? 'default';

            $result = $myobService->updateItemSOH($payload, $clientCode);

            if ($result['success']) {
                Log::info("MYOB SOH updated successfully for variant {$sourceVariant->id}, New SOH: {$stock}");

                // Update variant to mark MYOB sync complete
                $sourceVariant->update([
                    'myobsohpendingprocess' => 0,
                    'myobUpdated'           => now(),
                ]);
            } else {
                Log::error("MYOB SOH update failed: " . $result['message']);
            }

        } catch (\Exception $e) {
            Log::error("Error syncing SOH to MYOB: " . $e->getMessage());
        }
    }
}
