<?php
namespace Modules\Shopify\App\Traits;

use Illuminate\Support\Str;
use Modules\Shopify\App\Models\Source\SourceLocation;
use Modules\Shopify\App\Models\Source\SourceSoh;
use Modules\Shopify\App\Models\Source\SourceVariant;

trait ShopifyProductMutationTrait
{
    protected $live = 1;
    use ShopifyTrait;

    public function __construct()
    {}

    # this function is used to create or update product as per the shopifyProductId
    public function createOrUpdateProductMutation($productData)
    {

        $mutation = ' mutation {';

        if ($productData->shopifyProductId) {
            $productExists = $this->productExistsByShopifyId($productData->shopifyProductId);
            if ($productExists['exist'] != 1) {
                $productExists = $this->productExists($productData->handle);
            }
        } else {
            $productExists = $this->productExists($productData->handle);
        }

        print_r($productExists);

        // if (($productExists['exist'] == 0) && ($productData->status == 0)) return null; // Not send if product doesn't exist and isDisplayed is none
        // $status = $productData->active == 1 ? 'ACTIVE' : 'ARCHIVED'; // [active] field not present instead [status] is there

        if ($productExists['exist'] == 1) {
            $mutation .= 'productUpdate(input:{';
            $mutation .= 'id:"' . $productExists['handleID'] . '",';
            $status    = $productData->status == 1 ? 'ACTIVE' : 'ARCHIVED';
            if($productData->productType == 'wholesale') {
                $status = 'DRAFT';
            }
            // Operate existing variants & options
            // if (\request()->query('processtypechange') == 1) {
            //     $this->operateExistingData($productData); // For later use i.e if product type is converted (PRODUCT, MATRIX)
            // }
        } else {

            if ($productData->status == 0) {
                return 'inactive';
            }

            $mutation .= 'productCreate(input:{';
            $status    = 'DRAFT'; # default status for new products
                                  // $status = $productData->status == 1 ? 'ACTIVE' : 'ARCHIVED'; // [active] field not present instead [status] is there
            $mutation .= 'vendor:"' . $productData->vendor . '",';
            $mutation .= 'handle:"' . $this->filterHandle($productData->handle) . '",';
            #  $mutation .= 'productType:"' . $productData->productType . '",';
            // $status = ($productData->status == 1 ? 'ACTIVE' : 'ARCHIVED');
            // if (!empty($productData->productTags)) {
            //     $mutation .= 'tags:[' . $productData->productTags . '],';
            // }

            // Added by me
            if ($productData->isMatrix == 1) {
                $skuValue = $this->getOptionskeyValue($productData->id);
                if ($skuValue == null) {
                    return null;
                }

                if ($skuValue != null && $skuValue != '') {
                    $mutation .= $skuValue . ',';
                }
            }
        }

        if (! empty($productData->productTags)) {
            $mutation .= 'tags:[' . $productData->productTags . '],';
        }

        $mutation .= 'title:"' . $this->escapeDoubleQuotes($productData->title) . '",';
        $mutation .= 'descriptionHtml:"""' . $this->sanitizeString($productData->descriptionHtml) . '""",';

        if ($status != null) {
            $mutation .= 'status:' . $status;
        }

        // $variations = $this->createVariantsMutation($productData->variants, $productData->id);
        // $checkSoh = $this->getSumOfSoh($productData->variants);
        // if ($checkSoh <= 0) {
        //     $status = 'ARCHIVED';
        // }
        // if (empty($variations['variants']) && $productData->isMatrix == 1) {
        //     $status = 'ARCHIVED';
        // }

        // $mutation .= 'status:' . $status . ',';
        // check soh for all varinats

        // if ($productData->isMatrix == 1 && !empty($variations['optionName'])) {
        //     $mutation .= 'options:[' . $variations['optionName'] . '],';
        // }

        // #  dd($variations, $variations['optionName'], $variations['variants']);
        // if (!empty($variations['variants'])) {
        //     $mutation .= 'variants:[' . $variations['variants'] . ']'; // need to call createVariantsMutation function
        // }

        $mutation .= '}) {
                product {
                    id
                    handle
                    variants(first:100) {
                        edges {
                            node {
                                id
                                sku
                                title
                                inventoryItem{
                                    id
                                }

                            }
                        }
                    }
                }
             userErrors {
                field
                message
             }
            }
        }';
        return $mutation;
    }

    public function getOptionskeyValue($productID)
    {
        $variations = SourceVariant::where('product_id', $productID)->get();
        if ($variations->isEmpty()) {
            return null;
        }

        foreach ($variations as $variation) {
            $optionValues['color'][] = $variation->color;
            $optionValues['size'][]  = $variation->size;
        }
        $options = array_filter($optionValues, function ($values) {
            return ! empty(array_filter($values));
        });

// print_r($options);
        foreach ($options as $key => $value) {
            $result[$key] = array_unique($value);
        }
        return $this->generateProductOptions($result);
    }

    public function generateProductOptions(array $optionValues): string
    {
        dump('Option Values : ', $optionValues);
        $str   = 'productOptions: [';
        $count = 1;
        foreach ($optionValues as $key => $value) {
            $str .= '{
                        name: "' . $key . '",
                        position: ' . $count++ . ',
                        values: [';

            foreach ($value as $key1 => $val) {
                $str .= '{ name: "' . $val . '" }' . ($key1 == array_key_last($value) ? '' : ',');
            }

            $str .= '],
                  }' . ($key == array_key_last($optionValues) ? '' : ',');
        }
        $str .= ']';
        dump($str);
        return $str;
    }

    public function getSumOfSoh($variants)
    {
        $variantIds = [];
        foreach ($variants as $variant) {
            $variantIds[] = $variant->id;
        }
        $totalStock = SourceSoh::whereIn('variant_id', $variantIds)->where("currentStock", '>', 0)->sum('currentStock');

        return $totalStock;
    }
    public function productExistsByShopifyId($shopifyProductId)
    {

        $checkquery = '{
            product(id:"' . $shopifyProductId . '")
                {
                    id
                    title
                }
            }';

        $response = $this->sendShopifyQueryRequestV2("POST", $checkquery, $this->live);

        print_r($response);
        $handle['exist']    = 0;
        $handle['handleID'] = '';
        if ($response->data->product != null) {
            $handle['handleID'] = $response->data->product->id;
            $handle['exist']    = 1;
        }

        return $handle;
    }
    public function productExists($handel)
    {

        $code = $this->filterHandle($handel);

        $checkquery = '{
            productByHandle(handle: "' . $code . '")
                {
                    id
                    title
                    hasOnlyDefaultVariant
                    totalVariants
                    variants(first:1){
                    edges{
                        node{
                        id
                        }
                    }
                    }
                }
            }';

        $response = $this->sendShopifyQueryRequestV2("POST", $checkquery, $this->live);

        $handle['exist']    = 0;
        $handle['handleID'] = '';
        if ($response->data->productByHandle != null) {
            $handle['handleID'] = $response->data->productByHandle->id;
            $handle['exist']    = 1;
            // $handle['hasOnlyDefaultVariant'] = $response->data->productByHandle->hasOnlyDefaultVariant;
            // if ($response->data->productByHandle->hasOnlyDefaultVariant == true) {
            //     $handle["defaultVariantID"] = $response->data->productByHandle->variants->edges[0]->node->id;
            // }
        }
        return $handle;
    }
    private function filterHandle($handle)
    {
        $result = Str::lower($handle);
        $result = str_replace(' ', '', $result);
        $result = str_replace('.', '-', $result);
        $result = str_replace('+', '-', $result);

        return $result;
    }
    private function escapeDoubleQuotes(string $input): string
    {

        $input = str_replace("\\", "\\\\", $input);
        $input = str_replace('"', '\"', $input);
        // $input = str_replace("'", "\'", $input);

        return $input;
    }

    private function sanitizeString($string)
    {

        $sanitizedString    = preg_replace('/\\\\(?![0-7]{3})/', '', $string);
        $productDescription = mb_convert_encoding($sanitizedString, 'ASCII', 'UTF-8');
        $productDescription = str_replace('?', "", $productDescription);
        // Ensure UTF-8 encoding
        $productDescription = $this->escapeDoubleQuotes($productDescription);
        return $productDescription;
    }
    # this function will prepare the variants in structured way
    private function createVariantsMutation($variantDatas, $productId)
    {
        $variants      = [];
        $str           = [];
        $uniqueOptions = [];
        $optionName    = [];

        if (count($variantDatas) <= 0) {

            $variantDatas = SourceVariant::where('product_id', $productId)->get();
        }

        foreach ($variantDatas as $variant) {
            $options = [];

            if (isset($variant->color)) {

                $options[] = '"' . $variant->color . '"';
                if (! in_array('"Color"', $optionName)) {
                    $optionName[] = '"Color"';
                }
            }
            if (isset($variant->size)) {

                $options[] = '"' . $variant->size . '"';
                if (! in_array('"Size"', $optionName)) {
                    $optionName[] = '"Size"';
                }
            }

            $outputArray = collect($options)->map(function ($item) {

                return strval($item);
            })->toArray();

            $optionvalue = implode(',', $outputArray);

            if (! in_array($optionvalue, $uniqueOptions)) {
                $regularPrice           = $variant->priceWithTax;
                $comparePrice           = 0;
                $originalCompareAtPrice = $variant->compareAtPrice ?? 0;
                if ($variant->compareAtPrice < $regularPrice && $originalCompareAtPrice > 0) {
                    $regularPrice = $originalCompareAtPrice;
                    $comparePrice = $variant->priceWithTax;
                }

                $str = '{';

                $str .= 'sku: "' . $variant->sku . '",';

                $str .= 'price: "' . $regularPrice . '",';

                $str .= 'compareAtPrice: "' . $comparePrice . '",';

                $str .= 'inventoryPolicy:DENY,';

                #  $str .= 'weight:' . $variant->weight . ',';

                #   $str .= 'weightUnit:' . $variant->weightUnit . ',';

                $str .= 'barcode:"' . $variant->barcode . '",';

                $str .= 'inventoryItem:{';

                $str .= 'tracked:true';

                $str .= '},';

                if (! empty($optionvalue)) {

                    $str .= 'options:[' . $optionvalue . ']';
                }

                $str .= '}';

                $variants[]  = $str;

                $uniqueOptions[] = $optionvalue;
            }
        }
        $optionName = implode(',', $optionName);
        echo "Total No Of Variants " . count($variants);
        return [
            'variants'   => implode(',', $variants),
            'optionName' => $optionName,
        ];
    }

    # this fucntion will upadte the soh of the variants in Shopify using inventoryItemId
    public function updateProductSohMutation($sourceSohs, $inventoryItemId, $variantId, $sourceVarinatId, $product)
    {

        $sohQuery = $this->checkSohQuery($variantId);

        $response = $this->sendShopifyQueryRequestV2('POST', $sohQuery, $this->live);

        dump('current shopify soh : ', $response);
        if (isset($response->errors)) {
            return [
                'status'            => 0,
                'locationActivated' => 0,
                'error'             => json_encode($response->errors),
            ];
        }
        $res = [];
        if (isset($response->data->productVariant->inventoryItem)) {

            $res = $response->data->productVariant->inventoryItem->inventoryLevels->edges;
        }
        dump('Current SOH :', $res);
        $totalActivatedLocation = count($res);
        // dump('Total Activated Locations : ', $res, count($res));
        // dd('stop');
        // dd($totalActivatedLocation);
         if ($totalActivatedLocation ) {
        // if ($totalActivatedLocation == 8) {
            $mutation = '
            mutation {
                inventoryAdjustQuantities(
                    input: {
                        changes: [';
            $sum = 0;

            foreach ($sourceSohs as $sourceSoh) {
                // dd($sourceSoh);
                // $locationId = $sourceSoh->location->shopifyLocationId ?? null;
                // dump('locationId',$locationId);
                // dd($locationId);
                // echo $locationId . "=>" . $sourceSoh->location->name . "=>" . $sourceSoh->currentStock . "<br>";
//                 if ($locationId === "gid://shopify/Location/83408388377") {
// // dd('locationid');
//                     $sohDiff = 0;
//                 } else {
 $locationId = "gid://shopify/Location/74877730870";
                    $sum     += $sourceSoh->currentStock;
                    dump('sum',$sum);
                    // dd($sourceSoh->currentStock, $locationId, $res, $inventoryItemId);
                    $sohDiff  = $this->returnSohDiff($sourceSoh->currentStock, $locationId, $res, $inventoryItemId);
                //    dd('SOHDIFF',$sohDiff);
                // }
                $mutation .= '{
                                delta: ' . $sohDiff . ',
                                locationId: "' . $locationId . '",
                                inventoryItemId: "' . $inventoryItemId . '"
                            },';
            }
// dd($mutation);
            $mutation .= '         ],
                        reason:"correction",
                        name:"available",
                    }
                ) {
                    inventoryAdjustmentGroup {
                        id

                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';
            dump('Changes on Activated Location : ' , $mutation);
            return [
                'status'            => 2,
                'mutation'          => $mutation,
                'sumOfSoh'          => $sum,
                'locationActivated' => 0,
            ];
        } else {

            # dd(" Need to activate locations");
            $activatedLocation = [];
            foreach ($res as $al) {
                $activatedLocation[] = $al->node->location->id;
            }
            dump('Need to activate locations :', $activatedLocation);
            $needtoActivates = SourceLocation::whereNotIn('shopifyLocationId', $activatedLocation)->get();
            
            $total           = 0;
            foreach ($needtoActivates as $needtoActivate) {
                $query = SourceSoh::where("location_id", $needtoActivate->id)->where("variant_id", $sourceVarinatId);
                if ($product->isMatrix == 0) {
                    $query->where('product_id', $product->stockId);
                }
                $soh           = $query->first();
                $availableSoh  = $soh->currentStock ?? 0;
                dump('Available Stock in Source SOH : ', $availableSoh);
                $total += $availableSoh;
                if ($needtoActivate->shopifyLocationId == "gid://shopify/Location/83408388377") {
                    $availableSoh = 0;
                }
                if ($availableSoh < 0) {
                    $availableSoh = 0;
                }
                $this->activateInventoryLocation($inventoryItemId, $needtoActivate->shopifyLocationId, $availableSoh);
            }
            return [
                'status'            => 1,
                'locationActivated' => 1,
                'mutation'          => 'Activated Location',
                'sumOfSoh'          => $total,
            ];
        }
    }

    private function activateInventoryLocation($inventoryItemId, $shopifyLocationId, $stock)
    {
        $stock = $stock < 0 ? 0 : $stock;

        $mutation = 'mutation {
            inventoryActivate(
                inventoryItemId: "' . $inventoryItemId . '",
                locationId: "' . $shopifyLocationId . '",
                available :' . $stock . '

            ) {
              inventoryLevel {
                id
                quantities(names:"available") {
                  id
                  quantity
                }
                item {
                  id
                }
                location {
                  id
                }
              }
            }
          }';

        $response = $this->sendShopifyQueryRequestV2('POST', $mutation, $this->live);
        // dump($response);
    }

    private function returnSohDiff($sohinDB, $locationId, $resArray, $inventoryItemId)
    {
        $countLocation = count($resArray);
        $sohDiff       = 0;
        // dump("Before SOH Diff : ", $sohinDB, $locationId, $resArray);
        // if ($countLocation == 8) {
            foreach ($resArray as $res) {

                if ($res->node->location->id === $locationId) {
                    // dump('Location ID Matched', $res->node->location);
                    $sohinDB = $sohinDB < 0 ? 0 : $sohinDB;

                    $sohinShopify = $res->node->quantities[0]->quantity; // kailash
                                                                         // dump('Current SOH quantity in Shopify, pick [0] : ', $res->node->quantities);
                    if ($sohinShopify > $sohinDB) {

                        $sohDiff = 0 - ($sohinShopify - $sohinDB);
                    }

                    if ($sohinShopify < $sohinDB) {

                        $sohDiff = $sohinDB - $sohinShopify;
                    }
                    // dump('Actual SOH Diff : ', $sohDiff);
                    return $sohDiff;
                }
            }
            return $sohDiff;
        // }
        //  else {
        //     $acticatedLocation = [];
        //     foreach ($resArray as $res) {
        //         $acticatedLocation[] = $res->node->location->id;
        //     }
        //     $needtoActivates = SourceLocation::whereNotIn('shopifyLocationId', $acticatedLocation)->get();
        //     foreach ($needtoActivates as $needtoActivate) {
        //         $this->activateInventoryLocation($inventoryItemId, $needtoActivate->shopifyLocationId, $sohinDB);
        //     }
        // }
    }

    # create and update product media this function need to improve more
    public function createOrUpdateMediaMutation($productImages)
    {
        $mutation = 'mutation{';

        // if ($productImages[0]->shopifyMediaId) {

        //     $mutation .= 'productUpdateMedia(';
        // } else {
        // }

        $mutation .= 'productCreateMedia(';
        $mutation .= ' media: [';
        foreach ($productImages as $item) {

            $mutation .= '   {';
            $mutation .= ' alt:"' . $item->title ?? '' . '",';
            if ($item->shopifyMediaId) {

                $mutation .= ' id:"' . $item->shopifyMediaId . '",';
                $mutation .= ' previewImageSource:"' . $item->image . '",';
            } else {

                $mutation .= ' mediaContentType:IMAGE,';
                $mutation .= ' originalSource:"' . $item->image . '",';
            }

            $mutation .= ' },';
        }
        $mutation .= '],
                productId:"' . $productImages->shopifyProductId . '"
            ) {
                media {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }

    # attach product variants to the medias
    public function attachMediaMutation($ShopifyProductId, $mediaIds, $variantId)
    {
        $mutation = 'mutation {';

        $mutation .= 'productVariantAppendMedia(';
        $mutation .= 'productId: "' . $ShopifyProductId . '"';
        $mutation .= 'variantMedia: [';
        foreach ($mediaIds as $mediaId) {

            $mutation .= '   {';
            $mutation .= 'mediaId:"' . $mediaId . '",';
            $mutation .= 'productMediaIds:[' . $variantId . ']';
            $mutation .= '   },';
        }
        $mutation .= ']';
        $mutation .= '}) {
                media {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }

    # this function will change the status of the product in Shopify
    public function changeStatusMutation($shopifyProductId, $status)
    {
        $mutation  = 'mutation {';
        $mutation .= 'productUpdate(input:{';
        $mutation .= 'id:"' . $shopifyProductId . '",';
        $mutation .= 'status:' . $status . ',';
        $mutation .= '}) {
                product {
                    id
                    status
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }

    # this function will delete the product from  Shopify
    public function deleteProductMutation($shopifyProductId)
    {
        $mutation  = 'mutation {';
        $mutation .= 'productDelete(input:{';
        $mutation .= 'id:"' . $shopifyProductId . '"';
        $mutation .= '}) {
                product {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }
    # this function will delete the product's variants from  Shopify
    public function deleteVariantMutation($variantId)
    {
        $mutation  = 'mutation {';
        $mutation .= 'variantDelete(input:{';
        $mutation .= 'id:"' . $variantId . '"';
        $mutation .= '}) {
                variant {
                    id
                }
            userErrors {
                field
                message
            }
            }
        }';

        return $mutation;
    }
    public function updatePriceMutation($productId, $variants)
    {
        $mutation = '
        mutation {
               productVariantsBulkUpdate(productId:"' . $productId . '", variants:[';
        foreach ($variants as $variant) {
            // Check if the variant has a shopifyVariantId
            if ($variant->shopifyVariantId || isset($variant->shopifyVariantId)) {
                $regularPrice = $variant->priceWithTax;
                $comparePrice = 0;
                if ($variant->compareAtPrice < $regularPrice) {
                    $regularPrice = $variant->compareAtPrice;
                    $comparePrice = $variant->priceWithTax;
                }

                $mutation .= '{';
                $mutation .= 'id:"' . $variant->shopifyVariantId . '",';
                $mutation .= 'price:"' . $regularPrice . '",';
                $mutation .= 'compareAtPrice:"' . $comparePrice . '"';
                $mutation .= '},';
            }
        }
        // Remove the last comma if there's at least one variant
        if (! empty($variants)) {
            $mutation = rtrim($mutation, ',');
        }
        // Complete the mutation string
        $mutation .= ']) {
            product {
                id
            }
            productVariants {
                id
                price
                compareAtPrice
            }
            userErrors {
                field
                message
            }
        }
    }';

        return $mutation;
    }

    public function linkChannel($shopifyProductID)
    {

        $livePublication    = "gid://shopify/Publication/42605838405";
        $stagingPublication = "gid://shopify/Publication/76882575499";

        // $shopifyProductID = "gid://shopify/ProductVariant/41855293882507";
        $linkQuery = 'mutation {
            pub' . rand() . ': publishablePublish(
            id: "' . $shopifyProductID . '",
            input:
                [{
                    publicationId: "gid://shopify/Publication/118975562009",
                },
                {
                    publicationId: "gid://shopify/Publication/153784877337",
                },
                {
                    publicationId: "gid://shopify/Publication/153794773273",
                }]

            ){
            publishable {
                publicationCount
            }}}';

        $channelRes = $this->sendShopifyQueryRequestV2("POST", $linkQuery, $this->live);
        print_r($channelRes);
    }

    public function createVariationQuery($isUpdate, $variant, $product)
    {
        // Get Regular, Compare At prices
        $regular_price    = $variant->priceWithTax;
        $compare_price    = 0;
        $compare_at_price = $variant->compareAtPrice ?? 0;
        if ($variant->compareAtPrice < $regular_price && $compare_at_price > 0) {
            $regular_price = $compare_at_price;
            $compare_price = $variant->priceWithTax;
        }

        $vId = $isUpdate ? 'id:"' . $variant->shopifyVariantId . '", ' : '';

        $query = ' {
                        ' . $vId . '
                    barcode: "' . $variant->barcode . '",
                    compareAtPrice: "' . $compare_price . '",
                    price: "' . $regular_price . '",
                    inventoryItem: {
                        sku: "' . $variant->sku . '",';

        if ($isUpdate == false) {

            $current_options = [];
            if ($product->shopifyProductId != null) {
                $option_query = '{
                    product(id:"' . $product->shopifyProductId . '")
                        {
                            id
                            options {
                                id
                                name
                                values
                                position
                                optionValues {
                                    id
                                    name
                                    hasVariants
                                }
                            }
                        }
                    }';

                $response = $this->sendShopifyQueryRequestV2("POST", $option_query, $this->live);
                if (@$response->data->product != null) {
                    if ($response->data->product->options != null) {
                        foreach ($response->data->product->options as $row) {
                            $current_options[strtolower($row->name)] = $row->name;
                        }
                    }
                }
            }

            $query .= '
                    tracked: true,
                },
                inventoryPolicy: DENY,';

            $options = '';
            if ($variant->color) {
                $options = '{
                    optionName: "' . (! empty($current_options['color']) ? $current_options['color'] : 'color') . '",
                    name: "' . $variant->color . '"
                }';
            }

            if ($variant->size) {
                $options .= ', {
                    optionName: "' . (! empty($current_options['size']) ? $current_options['size'] : 'size') . '",
                    name: "' . $variant->size . '"
                }';
            }

            if ($options != '') {
                // dump('option create:', $options);
                $optionValues  = ' optionValues:[' . ltrim($options, ',') . ']';
                $query        .= $optionValues;
            }
        } else {
            $query .= '
                    },
                ';
        }

        $query .= ' }, ';

        return $query;
    }

    public function checkProductByHandle($handle_id, $product)
    {

        #First check using it's shopify id
        if (@$product->shopifyProductId != '') {
            $checkquery = '{
                product(id:"' . @$product->shopifyProductId . '")
                    {
                        id
                        title
                        hasOnlyDefaultVariant
                        totalVariants
                        variants(first:1){
                            edges{
                                node{
                                    id
                                }
                            }
                        }
                    }
                }';

            $response           = $this->sendShopifyQueryRequestV2("POST", $checkquery, $this->live);
            $handle['exist']    = 0;
            $handle['handleID'] = '';
            if (@$response->data->product != null) {
                dump("product found by Shopify ID");
                $handle['handleID'] = $response->data->product->id;
                $handle['exist']    = 1;

                $handle['hasOnlyDefaultVariant'] = $response->data->product->hasOnlyDefaultVariant;
                if ($response->data->product->hasOnlyDefaultVariant == true) {
                    $handle["defaultVariantID"] = $response->data->product->variants->edges[0]->node->id;
                }
            }
            return $handle;
        }

        $handle['exist'] = 0;
        $checkquery      = '{
            productByHandle(handle: "' . $handle_id . '")
                {
                    id
                    title
                    hasOnlyDefaultVariant
                    totalVariants
                    variants(first:1){
                    edges{
                        node{
                            id
                        }
                    }
                }
            }
        }';

        $response           = $this->sendShopifyQueryRequestV2("POST", $checkquery, $this->live);
        $handle['handleID'] = '';
        if ($response->data->productByHandle != null) {
            $handle['handleID']              = $response->data->productByHandle->id;
            $handle['exist']                 = 1;
            $handle['hasOnlyDefaultVariant'] = $response->data->productByHandle->hasOnlyDefaultVariant;
            if ($response->data->productByHandle->hasOnlyDefaultVariant == true) {
                $handle["defaultVariantID"] = $response->data->productByHandle->variants->edges[0]->node->id;
            }
        }
        return $handle;
    }

    public function getVariantQuery($isCreated = false, $productId, $variants = [])
    {
        if ($isCreated) {
            $variationQuery  = 'mutation {productVariantsBulkCreate(
                productId:"' . $productId . '",';
            $variationQuery .= 'strategy: REMOVE_STANDALONE_VARIANT,'; // REMOVE standalone variant ;
        } else {
            $variationQuery = 'mutation {productVariantsBulkUpdate(
                productId:"' . $productId . '",';
        }
        $variationQuery .= '
            variants: [ ' . $variants . '],';

        $variationQuery .= '
            ) {
                productVariants {
                    id
                    displayName
                    title
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
        return trim($variationQuery);
    }

    private function operateExistingData($product)
    {
        // Retrieve Option Values from Shopify
        $options = $this->getOptions($product->shopifyProductId);
        if (\request()->query('debug') == 'options') {
            dd(vars: $options);
        }

        if ($product->isMatrix == 1) {
            $this->createOptions($product);
        } else {
            if ($options['exist'] == 1) {

                // Product Variants
                $pId         = str_replace('gid://shopify/Product/', '', $product->shopifyProductId);
                $variant_ids = $this->checkProductVariantByProductID($pId);

                if (\request()->query('debug') == 'variantids') {
                    dd($variant_ids);
                }

                if ($variant_ids != []) {
                    // Remove All Variants
                    $this->removeAllVariants($product->shopifyProductId, $variant_ids);
                }

                if ($options['options'] != []) {
                    // Remove Option Values
                    $this->removeOptions($product->shopifyProductId, $options['options']);
                }
            }
        }
    }

    private function getOptions($productId)
    {
        $query = '{
            product(id:"' . $productId . '")
                {
                    id
                    options {
                        id
                        name
                        values
                        position
                        optionValues {
                            id
                            name
                            hasVariants
                        }
                    }
                }
            }';

        $result = [
            'exist'   => 0,
            'options' => null,
        ];
        $response = $this->sendShopifyQueryRequestV2("POST", $query, $this->live);
        if (@$response->data->product != null) {
            if ($response->data->product->options != null) {
                $result['exist'] = 1;

                $option_array = [];
                foreach ($response->data->product->options as $row) {
                    $option_array[] = $row->id;
                }
                $result['options'] = $option_array;
            }
        }
        dump('Current Options Values : ', $result);
        return $result;
    }

    private function createOptions($product)
    {
        $optionValues = $this->getOptionskeyValue($product->id);
        if ($optionValues != null && $optionValues != '') {
            $optionValues = str_replace("productOptions", "options", $optionValues);
            dump("Create Option Values : ", $optionValues);

            $query = 'mutation {
                productOptionsCreate (
                    productId: "' . $product->shopifyProductId . '",
                    ' . $optionValues . ',
                    variantStrategy: CREATE
                ) {
                    userErrors {
                        field
                        message
                        code
                    }
                    product {
                        id
                        options {
                            id
                            name
                            values
                            position
                            optionValues {
                                id
                                name
                                hasVariants
                            }
                        }
                    }
                }
            }';
            $response = $this->sendShopifyQueryRequestV2("POST", $query, $this->live);
            if (\request()->query('debug') == 'createOptions') {
                dd($query, $response);
            }

            if (@$response->data->product->options != null) {
                return true;
            }
            return false;
        }
    }

    private function removeOptions($shopifyProductId, $options)
    {
        $query = 'mutation {
            productOptionsDelete (
                productId: "' . $shopifyProductId . '",
                options: ["' . implode('","', $options) . '"]
            ) {
                userErrors {
                    field
                    message
                    code
                }
                deletedOptionsIds
                product {
                    id
                    options {
                        id
                        name
                        values
                        position
                        optionValues {
                            id
                            name
                            hasVariants
                        }
                    }
                }
            }
        }';
        dump('Remove Options : ', $query);

        $response = $this->sendShopifyQueryRequestV2("POST", $query, $this->live);
        if (@$response->data->product->options != null) {
            return true;
        }
        return false;
    }

    private function removeAllVariants($shopify_product_id, $variant_ids)
    {
        $query = 'mutation {
            productVariantsBulkDelete (
                productId: "' . $shopify_product_id . '",
                variantsIds: ["' . implode('","', $variant_ids) . '"]
            ) {
                product {
                    id
                    title
                }
                userErrors {
                    field
                    message
                }
            }
        }';
        dump('Remove Variants : ', $query);
        $response = $this->sendShopifyQueryRequestV2("POST", $query, $this->live);
        if (@$response->data->productVariantsBulkDelete->product->id != null) {
            return true;
        }
        return false;
    }

    public function removeOldProduct($product)
    {
        $isTypeChanged = true;
        $options       = $this->getShopifyOptions($product);
        if ($product->isMatrix == 1) {
            if (in_array('color', $options) || in_array('size', $options)) {
                $isTypeChanged = false;
            }

        } else {
            if (in_array('title', $options)) {
                $isTypeChanged = false;
            }

        }
        if ($isTypeChanged == true) {
            $delete_query = '
                mutation {
                    productDelete(input: {id: "' . $product->shopifyProductId . '"}) {
                        deletedProductId
                    }
                }
            ';
            $delete_product = $this->sendShopifyQueryRequestV2('POST', $delete_query, 1);
            dump('Delete Response : ', $delete_product);
            if (isset($delete_product->data->productDelete->deletedProductId)) {
                $product->shopifyProductId = null;
                $product->save();
                SourceVariant::where('product_id', $product->id)->update(['shopifyVariantId' => null, 'inventoryItemId' => 0]);
            }
        }
    }

    public function getShopifyOptions($product)
    {
        $query = 'query{
            product(id:"' . $product->shopifyProductId . '")
            {
                id
                options {
                    id
                    name
                    values
                    position
                    optionValues {
                        id
                        name
                        hasVariants
                    }
                }
            }
        }';
        $response = $this->sendShopifyQueryRequestV2('POST', $query, 1);
        $options  = [];
        if (isset($response->data->product->options)) {
            foreach ($response->data->product->options as $row) {
                $options[] = strtolower($row->name);
            }
        }
        return $options;
    }
}
