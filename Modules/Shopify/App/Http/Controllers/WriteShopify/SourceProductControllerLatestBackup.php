<?php
namespace Modules\Shopify\App\Http\Controllers\WriteShopify;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Shopify\App\Models\Source\SourceImage;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Models\Source\SourceSoh;
use Modules\Shopify\App\Models\Source\SourceVariant;
use Modules\Shopify\App\Services\SourceProductService;
use Modules\Shopify\App\Traits\ShopifyProductMutationTrait;
use Modules\Shopify\App\Traits\ShopifyTrait;

class SourceProductController extends Controller
{
    use ShopifyTrait, ShopifyProductMutationTrait;
    /**
     * Display a listing of the resource.
     */
    protected $live = 1;
    protected $productService;
    public function __construct(SourceProductService $productService)
    {
        $this->productService = $productService;
    }
    public function index(Request $request)
    {
        $debug = $request->input('debug', 0);
        $code  = $request->input('code', '');

        // die('temporary blocked');

        // $isManual = $request->input('isManual', 0);
        // if ($isManual == 0) {
        //     dd("is manual");
        // }
        // $dev = $request->input('dev', 0);
        // if ($dev == 0) {
        //     die('dev mode');
        // }
        try {
            if ($code) {
                $products = $this->productService->getProduct($code);
            } else {
                $products = $this->productService->getAllProduct(3);
            }
            if ($debug == 1) {
                dd($products);
            }
            if (count($products) <= 0) {
                echo 'no products found';

                // Temporary Disabled
                // SourceProduct::where('shopifyPendingProcess', 2)->update(['shopifyPendingProcess' => 1]);
                exit;
            }

            foreach ($products as $product) {
                $this->productService->updateProduct($product->id, [
                    'lastPushedDate' => date('Y-m-d H:i:s'),
                ]);
                echo "Product Title : " . $product->title . "<br>";
                echo "Product Handle : " . $product->handle . "<br>";

                // process type change
                // if (\request()->query('additional-operation') == 'look-for-type-change') {
                if ($product->shopifyProductId != null) {
                    $this->removeOldProduct($product);
                }

                // }

                $mutations = $this->createOrUpdateProductMutation($product);
                if ($mutations == null) {
                    $this->productService->updateProduct($product->id, [
                        'shopifyPendingProcess' => 3, // Errors
                        'lastPushedDate'        => date('Y-m-d H:i:s'),
                        'errorMessage'          => 'No variant found !!!',
                    ]);
                    continue;
                }
                if ($mutations == 'inactive') {
                    $this->productService->updateProduct(
                        $product->id,
                        [
                            'shopifyPendingProcess' => 0,
                            'lastPushedDate'        => date('Y-m-d H:i:s'),
                        ]);
                    continue;
                }

                // if ($mutations == 1) {
                //     $this->productService->updateProduct($product->id, [
                //         'shopifyPendingProcess' => 2,
                //         'lastPushedDate' => date('Y-m-d H:i:s')
                //     ]);
                //     continue;
                // }
                if ($debug == 2) {
                    dd($mutations);
                }

                $response = $this->sendShopifyQueryRequestV2('POST', $mutations, $this->live);
                print_r($response);
                if ($debug == 3) {
                    dd($response);
                }
                $errors = $response->data->productCreate->userErrors[0]->message ?? $response->data->productUpdate->userErrors[0]->message ?? null;

                if ($errors) {
                    $this->productService->updateProduct($product->id, [
                        'shopifyPendingProcess' => 3, // Errors
                        'lastPushedDate'        => date('Y-m-d H:i:s'),
                        'errorMessage'          => json_encode($errors),
                    ]);
                    continue;
                }

                # check for error in creating

                if (isset($response->data->productCreate) || isset($response->data->productUpdate)) {

                    # get product id
                    $shopifyProductId = $response->data->productCreate->product->id ?? $response->data->productUpdate->product->id;

                    $this->linkChannel($shopifyProductId);

                    echo "product id " . $shopifyProductId;
                    echo "<br>";
                    echo "product create or updated";

                    # get productvariants
                    // $variants =  $response->data->productCreate->product->variants->edges ?? $response->data->productUpdate->product->variants->edges ?? [];

                    // foreach ($variants as $variant) {
                    //     $variantId = $variant->node->id;
                    //     $sku = $variant->node->sku;
                    //     $updateVariants = [
                    //         'shopifyVariantId' => $variantId,
                    //         'inventoryItemId' => $variant->node->inventoryItem->id,
                    //     ];
                    //     $this->productService->updateVariants($sku, $updateVariants);
                    // }

                    $isCreated = false;
                    if (isset($response->data->productCreate->product->id)) {
                        $shopifyProductId = $response->data->productCreate->product->id;
                        $isCreated        = true;
                    } else {
                        $shopifyProductId = $response->data->productUpdate->product->id;
                    }

                    # update productdata
                    $updateData = [
                        'pricePendingProcess' => 0,
                        'lastPushedDate'      => date('Y-m-d H:i:s'),
                        'shopifyProductId'    => $shopifyProductId,
                    ];
                    // if ($product->sohPendingProcess == 3 || $product->sohPendingProcess == 2) {
                    //     $updateData['sohPendingProcess'] = 1;
                    //     $updateData['imagePendingProcess'] = 1;
                    //     $updateData['varinatsAppendPending'] = 1;
                    // }

                    if ($isCreated) {
                        $updateData['shopifyPendingProcess']   = 4; // Success i.e need to update later after variation process
                        $updateData['shopifyVariationProcess'] = 1; // Next step process variants
                    } else {
                        $updateData['shopifyPendingProcess']   = 0;
                        $updateData['shopifyVariationProcess'] = 1; // Next step process variants
                    }

                } else {
                    $updateData = [
                        'shopifyPendingProcess' => 3, # error
                        'lastPushedDate'        => date('Y-m-d H:i:s'),
                        'errorMessage'          => ! empty($response->errors) ? json_encode($response->errors) : null,
                    ];
                }
                dump($updateData, $product->id);
                $res = $this->productService->updateProduct($product->id, $updateData);
                dump($res);
            }

            echo "Process Completed";
        } catch (Exception $th) {
            dd($th);
        }
    }

    public function pushVariants(Request $request)
    {
        die('Not Used');
        $debug = $request->input('debug', 0);
        $code  = $request->input('code', '');
        $limit = $request->input('limit', 3);
        try {
            $products = $code ? $this->productService->getProduct($code) : $this->productService->getAllVariationPending($limit);
            if ($debug == 1) {
                dd($products);
            }
            if ($products->isEmpty()) {
                return response()->json(['message' => 'No pending product variants found'], 404);
            }

            $responses = [];
            foreach ($products as $product) {

                DB::beginTransaction();
                $mutations = $this->variantsMutation($product, false, false);

                if ($debug == 2) {
                    dd($mutations);
                }

                if (! $mutations) {
                    continue;
                }

                $response                       = $this->sendShopifyQueryRequestV2('POST', $mutations, $this->live);
                $responses[$product->productID] = $response;

                if ($debug == 3) {
                    dd($response);
                }

                $errors = $response->data->productVariantsBulkCreate->userErrors[0]->message ?? $response->data->productVariantsBulkUpdate->userErrors[0]->message ?? null;
                if ($errors) {
                    $product->update([
                        'shopifyPendingProcess' => 4,
                        'lastPushedDate'        => date('Y-m-d H:i:s'),
                        'updated_at'            => date('Y-m-d H:i:s'),
                        'errorMessage'          => json_encode($errors),
                    ]);
                    DB::commit();
                    continue;
                }

                // check for error in creating
                if (isset($response->data->productVariantsBulkCreate) || isset($response->data->productVariantsBulkUpdate)) {
                    $updateData = [
                                                        // 'shopifyPendingProcess' => 0, // Success
                        'shopifyVariationProcess' => 0, // Success
                        'lastPushedDate'          => date('Y-m-d H:i:s'),
                        // 'shopifyProductId' => @$response->data->productVariantsBulkCreate->productVariants->id ?? $response->data->productVariantsBulkUpdate->productVariants->id,
                        // 'ShopifyVariationID' => json_encode(@$response->data->productCreate->product->variants->edges ?? $response->data->productUpdate->product->variants),
                        'updated_at'              => date('Y-m-d H:i:s'),
                    ];
                    $this->updateSourceVariants(@$response->data->productVariantsBulkCreate->productVariants); // update variantId in source_variants
                } else {
                    $updateData = [
                        'shopifyPendingProcess' => 3, // error
                        'lastPushedDate'        => date('Y-m-d H:i:s'),
                        'updated_at'            => date('Y-m-d H:i:s'),
                    ];
                }
                $product->update($updateData);
                DB::commit();
            }

            return response()->json([
                'message' => 'Product Variants Synced Successfully.',
                'data'    => $responses,
            ]);
        } catch (Exception $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function pushVariantsV1(Request $request)
    {
        // die('test');
        $debug = $request->input('debug', 0);
        $code  = $request->input('code', '');
        $limit = $request->input('limit', 4);

        // die('temporary blocked');

        try {
            $products = $code ? $this->productService->getProductsV1($code, null) : $this->productService->getProductsV1(null, $limit);
            if ($debug == 1) {
                dd($products);
            }
            if ($products->isEmpty()) {
                return response()->json(['message' => 'No pending product variants found !!!'], 404);
            }

            foreach ($products as $product) {

                // DB::beginTransaction();

                $shopify_variation_ids      = [];
                $create_variation_mutataion = $update_variation_mutation = '';
                if ($product->isMatrix == 1) {

                    if (count($product->variants) > 0) {

                        // Get all shopify variants list
                        $shopify_product_id = $product->shopifyProductId ? str_replace('gid://shopify/Product/', '', $product->shopifyProductId) : null;
                        if ($shopify_product_id != null) {
                            $shopify_variation_ids = $this->checkProductVariantByProductID($shopify_product_id);
                        }

                        // dd($shopify_variation_ids);
                        foreach ($product->variants as $key => $variation) {

                            $variant_exist = false;
                            // if ($variation->displayInWebshop === 0 || ($variation->status != null && $variation->status != 'ACTIVE')) {
                            //     continue;
                            // }
                            $is_webactive = $variation->displayInWebshop === 0;
                            $is_inactive  = $variation->status !== null && $variation->status !== 'ACTIVE';

                            if ($is_webactive || $is_inactive) {
                                dump('skip');
                                continue;
                            }

                            if ($variation->shopifyVariantId != null) {
                                if (in_array($variation->shopifyVariantId, $shopify_variation_ids)) {
                                    $variant_exist = true;
                                }
                            }
                            $each_variation_query = $this->createVariationQuery($variant_exist, $variation, $product);

                            if ($variant_exist == true) {
                                $update_variation_mutation .= $each_variation_query;
                            } else {
                                $create_variation_mutataion .= $each_variation_query;
                            }
                        }
                    }
                } else {

                    $handle        = $this->checkProductByHandle($product->handle, $product);
                    $product_exist = $handle['exist'];

                    $product_variant = '{';

                    $is_update = false;
                    if ($product_exist == 1 && $handle["hasOnlyDefaultVariant"] == true) {
                        $product_variant .= 'id:"' . $handle["defaultVariantID"] . '",';
                        $is_update        = true;
                    }

                    $regular_price    = $product->priceWithTax ? $product->priceWithTax : $product->price;
                    $compare_price    = 0;
                    $compare_at_price = $product->compareAtPrice ?? 0;
                    if ($product->compareAtPrice < $regular_price && $compare_at_price > 0) {
                        $temp_regular_price = $compare_at_price;
                        $compare_price      = $regular_price;
                        $regular_price      = $temp_regular_price;
                    }

                    if ($regular_price != null && $regular_price != '') {
                        $product_variant .= 'price: "' . $regular_price . '",';
                    }

                    if ($product->barcode) {
                        $product_variant .= 'barcode: "' . $product->barcode . '",';
                    }

                    $product_variant .= 'compareAtPrice: "' . $compare_price . '",';

                    $product_variant .= 'inventoryItem: { sku: "' . $product->handle . '", ';

                    if ($is_update == false) {
                        $product_variant .= '
                                tracked: true,
                            },
                            inventoryPolicy: DENY,';
                    } else {
                        $product_variant .= '}';
                    }

                    $product_variant .= '}';
                }

                $create_variants_query = $update_variants_query = null;
                if ($product->isMatrix == 1) {
                    $create_variants_query = $create_variation_mutataion;
                    $update_variants_query = $update_variation_mutation;
                }

                if ($product->isMatrix == 0) {
                    if ($is_update == true) {
                        $update_variants_query = $product_variant;
                    } else {
                        $create_variants_query = $product_variant;
                    }
                }

                if ($create_variants_query != null && $create_variants_query != '') { // For Create/Add
                    $create_query = $this->getVariantQuery(true, $product->shopifyProductId, $create_variants_query);
                    dump('Create Mutation : ', $create_query);
                    if ($debug == 'create') {
                        dd($create_query);
                    }

                    $response_create = $this->sendShopifyQueryRequestV2('POST', $create_query, $this->live);
                    if ($debug == 'createResponse') {
                        dd($response_create);
                    }

                    if (isset($response_create->data->productVariantsBulkCreate)) {
                        $variantData = @$response_create->data->productVariantsBulkCreate->productVariants;
                        $this->updateVariationProduct($variantData);
                    }
                }

                if ($update_variants_query != null && $update_variants_query != '') { // For Update
                    $update_query = $this->getVariantQuery(false, $product->shopifyProductId, $update_variants_query);
                    dump('Update Mutation : ', $update_query);
                    if ($debug == 'update') {
                        dd($update_query);
                    }

                    $update_response = $this->sendShopifyQueryRequestV2('POST', $update_query, $this->live);
                    if ($debug == 'updateResponse') {
                        dd($update_response);
                    }

                    if (isset($update_response->data->productVariantsBulkUpdate)) {
                        $variantData = @$update_response->data->productVariantsBulkUpdate->productVariants;
                        $this->updateVariationProduct($variantData);
                    }
                }

                // Remove archived variants i.e applies to matrix products only
                if ($product->isMatrix == 1 && count($shopify_variation_ids) > 0) {
                    $this->removeArchivedVariants($product->shopifyProductId, $product->id, $debug, $shopify_variation_ids); // Removing archived variants
                }

                $updateData  = [
                    'lastPushedDate'          => date('Y-m-d H:i:s'),
                    'updated_at'              => date('Y-m-d H:i:s'),
                    'shopifyVariationProcess' => 0,
                    'sohPendingProcess'       => 1,
                    'sohResyncTryCount'       => 0,
                    'emailSentCount'          => 0,
                ];
         
                // Process the newly created product matrix (i.e shopifyPendingProcess = 4)
                if ($product->shopifyPendingProcess == 4) {
                    // dd('update here');
                    if ($product->status == 1 && $product->shopifyProductId != null) {

                        $change_query = $this->changeStatusMutation($product->shopifyProductId, 'ACTIVE');
                        dump('Status Mutation : ', $change_query);
                        $response_status_update = $this->sendShopifyQueryRequestV2('POST', $change_query, $this->live);
                        if ($debug == 'statusResponse') {
                            dd($response_status_update);
                        }

                    }
                    $updateData['shopifyPendingProcess'] = 0;
                }

                $product->update($updateData);
                // DB::commit();
            }

            return response()->json([
                'message' => 'Product Variants Synced Successfully !!!',
            ]);
        } catch (Exception $th) {
            // DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function updateVariationProduct($variantData)
    {
        print_r($variantData);
        if (! empty($variantData)) {
            if (count($variantData) > 0) {
                foreach ($variantData as $var) {
                    SourceVariant::where('sku', $var->inventoryItem->sku)->update([
                        // 'shopifyPendingProcess' => 0,
                        'shopifyVariantId' => $var->id,
                        'inventoryItemId'  => @$var->inventoryItem->id ?? 0,
                    ]);
                }
            }
        }
    }

    public function removeArchivedVariants($shopifyProductIDFull, $parentProductId, $debug, $shopify_variation_ids)
    {
        $archivedVariants = SourceVariant::where(function ($query) {
            $query->where('status', 'ARCHIVED')
                ->orWhere('displayInWebshop', 0);
        })
            ->where('displayInWebshop', '<>', null)
            ->where('shopifyVariantId', '<>', null)
            ->where('product_id', $parentProductId)
            ->whereIn('shopifyVariantId', $shopify_variation_ids)
            ->get();

        if ($debug == 'inactiveVariants') {
            dd($archivedVariants, $shopify_variation_ids);
        }

        if ($archivedVariants->isEmpty()) {
            return null;
        }

        $variantIds = $archivedVariants->pluck('shopifyVariantId');

        if (! empty($variantIds)) {
            $deleteVariationQuery = $this->deleteShopifyVariants($shopifyProductIDFull, $variantIds);
            dump('Delete Variation:', $deleteVariationQuery);

            if ($debug == 'delete') {
                dd($deleteVariationQuery);
            }

            $response = $this->sendShopifyQueryRequestV2("POST", $deleteVariationQuery, $this->live);
            if ($debug == 'deleteResponse') {
                dd($response);
            }

            if (isset($response->data->productVariantsBulkDelete->product)) {
                SourceVariant::whereIn('shopifyVariantId', $variantIds)->update(['shopifyPendingProcess' => 0, 'shopifyVariantId' => null, 'inventoryItemId' => 0]);

                // $totalVariantCount = SourceVariant::where('product_id', $parentProductId)->count();
                // $totalArchivedVariantCount = SourceVariant::where('product_id', $parentProductId)->where(function ($query) {
                //     $query->where('status', 'ARCHIVED')
                //         ->orWhere('displayInWebshop', 0);
                //     })->count();

                // if (\request()->query('check-matrix') == 'true') {
                //     if ($totalArchivedVariantCount == $totalVariantCount) {
                //         $this->removeProductMatrix($parentProductId);
                //     }
                // }
            }
        }
    }

    public function removeProductMatrix($parentProductId)
    {
        $product = SourceProduct::where('id', $parentProductId)->first();
        if ($product) {
            // remove product from shopify
            $remove_query = 'mutation {
                    productDelete(input: {id: "' . $product->shopifyProductId . '"}) {
                        deletedProductId
                        userErrors {
                            field
                            message
                        }
                    }
                }';
            dump('Remove Product Mutation : ', $remove_query);
            $this->sendShopifyQueryRequestV2('POST', $remove_query, $this->live);
        }
    }

    public function deleteShopifyVariants($productId, $variantIds)
    {
        $query = '
            mutation {
                productVariantsBulkDelete(productId: "' . $productId . '", variantsIds: ' . $variantIds . ') {
                    product {
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';

        return $query;
    }

    private function checkProductVariantByProductID($productId)
    {
        $checkquery = 'query {
            productVariants(query: "product_id:' . $productId . '", first: 100) {
                edges {
                    node {
                        id
                        sku
                        title
                        product {
                            id
                            title
                            handle
                        }
                    }
                }
            }
        }';
        $response = $this->sendShopifyQueryRequestV2("POST", $checkquery, $this->live);
        if (\request()->query('debug') == 'currentVariants') {
            dd($checkquery, $response);
        }

        $variants = [];
        if ($response->data->productVariants->edges != null) {
            foreach ($response->data->productVariants->edges as $row) {
                $variants[] = $row->node->id;
            }
        }
        return $variants;
    }

    public function updateArchivedVariants(Request $request)
    {
        $debug = $request->input('debug', 0);
        $code  = $request->input('code', '');
        $limit = $request->input('limit', 3);
        try {
            $products = $code ? $this->productService->getPreviousDeletedVariantProducts($code, 1) : $this->productService->getPreviousDeletedVariantProducts(null, $limit);
            if ($debug == 1) {
                dd($products);
            }
            if ($products->isEmpty()) {
                // SourceProduct::where('shopifyPendingProcess', 3)->update(['shopifyPendingProcess' => 1]);
                return response()->json(['message' => 'No pending archived product variants found'], 404);
            }

            $responses = [];
            foreach ($products as $product) {

                DB::beginTransaction();
                $mutations = $this->variantsMutation($product, false, true);
                if (! $mutations) {
                    return null;
                }

                if ($debug == 2) {
                    dd($mutations);
                }

                $response    = $this->sendShopifyQueryRequestV2('POST', $mutations, $this->live);
                $responses[] = $response;
                if ($debug == 3) {
                    dd($responses);
                }

                $errors = $response->data->productVariantsBulkCreate->userErrors[0]->message ?? $response->data->productVariantsBulkUpdate->userErrors[0]->message ?? null;
                if ($errors) {
                    $product->update([
                        'shopifyPendingProcess' => 4,
                        'lastPushedDate'        => date('Y-m-d H:i:s'),
                        'updated_at'            => date('Y-m-d H:i:s'),
                        'errorMessage'          => json_encode($errors),
                    ]);
                    DB::commit();
                    continue;
                }

                // check for error in creating
                if (isset($response->data->productVariantsBulkCreate) || isset($response->data->productVariantsBulkUpdate)) {
                    $updateData = [
                        'shopifyVariationProcess' => 0, // Success
                        'lastPushedDate'          => date('Y-m-d H:i:s'),
                        'updated_at'              => date('Y-m-d H:i:s'),
                    ];
                    $this->updateSourceVariants(@$response->data->productVariantsBulkCreate->productVariants); // update variantId in source_variants
                } else {
                    $updateData = [
                        'shopifyPendingProcess' => 3, // Error
                        'lastPushedDate'        => date('Y-m-d H:i:s'),
                        'updated_at'            => date('Y-m-d H:i:s'),
                    ];
                }
                $product->update($updateData);
                DB::commit();
            }

            return response()->json([
                'message' => 'Active Product Variants Synced Successfully.',
                'data'    => $responses,
            ]);
        } catch (Exception $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function updateSourceVariants($variants = null)
    {
        if ($variants == null) {
            return null;
        }

        foreach ($variants as $var) {
            $variant = SourceVariant::where('sku', $var->sku)->first();
            if ($variant) {
                // $variant->shopifyPendingProcess = 1; // Must be 1 so the its soh will be syncing
                $variant->shopifyVariantId = $var->id;
                $variant->inventoryItemId  = @$var->inventoryItem->id ?? null;
                $variant->isManualDeleted  = 0;
                $variant->save();
            }
        }
    }

    public function deletefromShopify(Request $request)
    {
        $debug          = $request->get('debug') ?? 0;
        $limit          = $request->get('limit') ?? 10;
        $sourceProducts = SourceProduct::where('isDeleted', 1)->take($limit)->get();
        if ($debug == 1) {
            dd($sourceProducts);
        }
        if ($sourceProducts->isEmpty()) {
            return response()->json(['message' => 'No data found'], 404);
        }
        $res = [];
        foreach ($sourceProducts as $sourceProduct) {
            DB::beginTransaction();
            $query = $this->deleteShopifyProduct($sourceProduct->shopifyProductId);
            if ($debug == 2) {
                dd($query);
            }
            $response = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
            $res[]    = $response;
            if ($debug == 3) {
                dd($response);
            }
            if (isset($response->data->productDelete->deletedProductId)) {
                SourceSoh::where('product_id', $sourceProduct->id)->delete();
                SourceImage::where('product_id', $sourceProduct->id)->delete();
                SourceVariant::where('product_id', $sourceProduct->id)->delete();
                $sourceProduct->delete();
            }
            DB::commit();
        }
        return response()->json(['message' => 'success', 'response' => $res], 200);
    }

    public function deleteVariantFromShopify(Request $request)
    {
        $debug = $request->get('debug') ?? 0;
        $limit = $request->get('limit') ?? 10;

        $sourceVariants = SourceVariant::with('sourceProduct')->where([
            'status'          => 'ARCHIVED',
            'isManualDeleted' => 0,
        ])->take($limit)->get();
        if ($debug == 1) {
            dd($sourceVariants);
        }

        if ($sourceVariants->isEmpty()) {
            return response()->json(['message' => 'No ARCHIVED Product Variants Found!'], 404);
        }
        $res = [];
        foreach ($sourceVariants as $variant) {
            DB::beginTransaction();
            if ($variant->shopifyVariantId != null) {
                continue;
            }
            // SKIP, if NO shopifyVariantId

            $productId  = $variant->sourceProduct->shopifyProductId;
            $variantIds = [$variant->shopifyVariantId];

            if ($productId == null || $variantIds == []) {
                continue;
            }

            $query = $this->deleteShopifyVariant($productId, $variantIds);
            if ($debug == 2) {
                dd($query);
            }

            $response = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
            $res[]    = $response;
            if ($debug == 3) {
                dd($response);
            }

            $variant->isManualDeleted = 1;
            $variant->save();
            // $variant->delete(); // Remove Variant
            DB::commit();
        }
        return response()->json(['message' => 'success', 'response' => $res], 200);
    }

    public function variantsMutation($productData, $isUpdate = false, $processArchived = false)
    {
        $stratege = ($isUpdate) ? '' : ', strategy: REMOVE_STANDALONE_VARIANT';
        $varinats = $this->createVariantsMutationNew($productData, $isUpdate, $processArchived);
        if ($varinats == null) {
            return null;
        }

        $query = '
            mutation { ';

        if ($isUpdate == true) {
            $query .= 'productVariantsBulkUpdate (';
        } else {
            $query .= 'productVariantsBulkCreate (';
        }
        $query .= '
                    productId: "' . $productData->shopifyProductId . '",
                    variants: [ ' . $varinats['variants'] . ']
                    ' . $stratege . '
                ) {
                productVariants {
                    id
                    inventoryItem {
                        id
                        sku
                    }
                }
                userErrors {
                    code
                    field
                    message
                }
            }
        }';

        return $query;
    }

    private function createVariantsMutationNew($product, $isupdate = false, $processArchived = false)
    {

        $variants = [];
        $str      = [];

        $variantDatas = [];
        if ($product->isMatrix == 1) {
            $sql = SourceVariant::where('product_id', $product->id)
                ->where('shopifyPendingProcess', 1)
                ->where('status', '<>', 'ARCHIVED');

            if ($processArchived) {
                $sql->where('isManualDeleted', 1);
            } else {
                $sql->where('isManualDeleted', 0);
            }
            if ($isupdate == true) {
                $sql->whereNotNull('shopifyVariantId');
            } else {
                $sql->whereNull('shopifyVariantId');
            }
            $variantDatas = $sql->get();
        } else {
            $variantDatas[0] = $product;
        }

        if ($variantDatas == []) {
            return null;
        }

        foreach ($variantDatas as $variant) {

            $regularPrice           = $variant->priceWithTax ?? 0;
            $comparePrice           = 0;
            $originalCompareAtPrice = $variant->compareAtPrice ?? 0;
            if ($variant->compareAtPrice < $regularPrice && $originalCompareAtPrice > 0) {
                $regularPrice = $originalCompareAtPrice;
                $comparePrice = $variant->priceWithTax;
            }

            // option values arrange
            $options      = '';
            $optionValues = '';
            if ($product->isMatrix == 1) {
                if ($variant->color) {
                    $options = '{
                        optionName: "color",
                        name: "' . $variant->color . '"
                    }';
                }
                if ($variant->size) {
                    $options .= ', {
                        optionName: "size",
                        name: "' . $variant->size . '"
                    }';
                }
                $optionValues = ' optionValues:[' . ltrim($options, ',') . ']';
                $sku          = $variant->sku;
            } else {
                $sku = $product->handle;
            }

            $VID  = $isupdate ? 'id:' . $variant->shopifyVariantId . ', ' : ''; // Needs to be done for SIMPLE product update since shopifyVariantId doesn't exist in matrix product

            $str = ' {
                        ' . $VID . '
                    barcode: "' . $variant->barcode . '",
                    compareAtPrice: "' . $comparePrice . '",
                    price: "' . $regularPrice . '",
                    inventoryItem: {
                        sku: "' . $sku . '",';

            if ($isupdate == false) {
                $str .= '
                        tracked: true,
                    },
                    inventoryPolicy: DENY,';
            } else {
                $str .= '
                    },
                ';
            }

            if ($optionValues) {
                $str .= $optionValues;
            }
            $str        .= ' }';
            $variants[] = $str;
        }

        echo 'Total No Of Variants ' . count($variants);
        return [
            'variants' => implode(',', $variants),
        ];
    }
}
