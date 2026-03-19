<?php

namespace Modules\Shopify\App\Http\Controllers\Middleware\Erply;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Shopify\App\Models\ErplyModel\Product;
use Modules\Shopify\App\Services\ErplyService\ErplyProductService;
use Modules\Shopify\App\Services\SourceService\SourceProductGetService;

class PriceController extends Controller
{
    protected $productService;
    protected $sourceProductService;
    public function __construct(ErplyProductService $productService)
    {
        $this->productService = $productService;
        $this->sourceProductService = new SourceProductGetService();
    }

    public function indexBackup(Request $request)
    {
        try {
            $debug = $request->input('debug', 0);
            $code = $request->input('code');
            $limit = $request->input('limit', 20);
            // $isManual = $request->input('isManual', 0);
            // if ($isManual == 0) {
            //     dd("is manual");
            // }
            $getdata  = [
                'roadhousePricePending' => 1
            ];
            if (isset($code)) {
                $getdata = [
                    'code' => $code
                ];
            }
            DB::beginTransaction();
            # get product details from erplay
            $products = $this->productService->getProducts($getdata, $limit, $code);
            if ($debug == 1) {
                dd($products);
            }
            if (count($products) > 0) {
                foreach ($products as $product) {

                    $count = Product::where('code', $product->code)->count();
                    if ($count > 1) {
                        echo "duplicate product found for code " . $product->code . " <br>";
                        $product->update(['roadhousePricePending' => 4]);
                        $product = $this->getduplicateProducts($product);
                    }
                    #get  details from the erply
                    echo "Product ID : " . $product->productID;
                    echo "<br>";

                    echo "Product code : " . $product->code;
                    echo "<br>";

                    $Variants =   $this->productService->getAllVariants($product->productID);
                    echo "Variants Count : " . count($Variants);
                    echo "<br>";

                    if ($Variants) {
                        foreach ($Variants as $Variant) {
                            $prices = [];
                            // $prices['special_price_withvat'] = null;
                            // $prices['normal_price_withvat'] = null;
                            // $discounts = [];
                            // $discounts['brand_discount_Per'] = null;
                            // $discounts['special_discount_per'] = null;

                            echo "Variant/Product ID : " . $Variant->productID;

                            // $prices[] = $Variant->priceWithVat;

                            #get  variant details from erplay
                            $specialPriceLists = $this->productService->getSpecialPriceList($Variant->productID);
                            if ($specialPriceLists) {
                                foreach ($specialPriceLists as $specialPriceList) {

                                    $priceRules = $this->productService->getPriceLists($specialPriceList->pricelistID);

                                    if ($priceRules) {
                                        $ckeckAssignedToweareHouse = $this->productService->checkAssignedToWareHouse($priceRules->pricelistID);
                                        if ($debug == 2) {

                                            dd($ckeckAssignedToweareHouse);
                                        }
                                        if ($ckeckAssignedToweareHouse) {
                                            echo "Price Rules startDate : " . $priceRules->startDate . '<br>';

                                            echo "Price Rules endDate : " . $priceRules->endDate . '<br>';
                                            if (
                                                $priceRules->startDate == '0000-00-00' ||
                                                ($priceRules->startDate <= date('Y-m-d')  && $priceRules->endDate >= date('Y-m-d'))
                                            ) {
                                                echo 'Processing Price List ID- ' . $specialPriceList->pricelistID . '<br>';
                                                echo "have special price" . '<br>';
                                                // append special price to array
                                                $prices[] = $specialPriceList->priceWithVat;
                                            } else {
                                                echo "no special price" . '<br>';
                                            }
                                        } else {
                                            echo "Price Rules not assigned to warehouse" . '<br>';
                                        }
                                        # dump($priceRules);
                                    } else {

                                        echo "No price rules found" . '<br>';
                                    }
                                }
                            } else {

                                echo "No Special Price found" . '<br>';
                            }
                            // price comparison
                            $sourceVarient = $this->sourceProductService->getSourceVariants(['sku' => $Variant->code]);

                            // $compareAtPrice = $sourceVarient->compare_at_price ?? 0;
                            if ($product->brandID) {
                                $brandDiscount = $this->productService->getBrandDiscount($product->brandID);

                                if ($brandDiscount) {
                                    $ckeckAssignedToweareHouse = $this->productService->checkAssignedToWareHouse($brandDiscount->priceListID);
                                    if ($ckeckAssignedToweareHouse) {
                                        echo "brand discount is " . $brandDiscount->discount . "%";
                                        $priceRulesDiscount = $this->productService->getPriceLists($brandDiscount->priceListID);

                                        if ($priceRulesDiscount) {
                                            if (
                                                $priceRulesDiscount->startDate == '0000-00-00' ||
                                                ($priceRulesDiscount->startDate <= date('Y-m-d')  && $priceRulesDiscount->endDate >= date('Y-m-d'))
                                            ) {
                                                $finalDiscountPrice = $Variant->priceWithVat - ($Variant->priceWithVat * ($brandDiscount->discount / 100));

                                                $prices[] = $finalDiscountPrice;
                                            }
                                        } else {
                                            echo "no brand discount price rules found";
                                        }
                                    }
                                } else {
                                    echo "no brand discount";
                                }
                            } else {
                                echo "no brand discount";
                            }
                            $isOfferPrice = 0;
                            $minPrice = 0;
                            if (count($prices) > 0) {
                                $isOfferPrice = 1;
                                // here we need to check the smallest value
                                $minPrice = min($prices);

                                echo "min price =" . $minPrice;
                            }

                            print_r($prices);
                            if ($debug == 2) {
                                dump($minPrice);
                                dd($prices);
                            }

                            if ($isOfferPrice == 1) {
                            }
                            $datas = [
                                // 'priceWithTax' => $isOfferPrice == 1 ? $minPrice : $Variant->priceWithVat,
                                // 'compareAtPrice' => $isOfferPrice == 1 ? $Variant->priceWithVat : 0,
                                'compareAtPrice' => $minPrice,
                                'pricePendingProcess' => 1

                            ];

                            $updateResult = $this->sourceProductService->updateSourceVariants(['sku' => $Variant->code], $datas);

                            if ($updateResult) {
                                echo "Variant Updated =>" . $sourceVarient->sku;
                            } else {

                                echo "Variant Not Updated";
                            }
                        }

                        $updateData = [
                            'roadhousePricePending' => 0

                        ];
                        $this->sourceProductService->updateSourceProduct(
                            ['stockId' => $product->productID],
                            ['pricePendingProcess' => 1, 'shopifyPendingProcess' => 1]
                        );
                    } else {
                        echo "no variant found";
                        $updateData = [
                            'roadhousePricePending' => 3

                        ];
                    }


                    $this->productService->updateProducts($product->productID, $updateData);

                    echo "Procerss Completed for Product ID : " . $product->productID;
                }

                DB::commit();
                echo "Whole Process Completed";
            } else {
                echo "no shopifyPricePending products found";
            }
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            return $e->getMessage();
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $debug = $request->input('debug', 0);
            $code = $request->input('code');
            $pricelistId = $request->input('pricelistid');
            $limit = $request->input('limit', 20);
            // $isManual = $request->input('isManual', 0);
            // if ($isManual == 0) {
            //     dd("is manual");
            // }
            $getdata  = [
                'roadhousePricePending' => 1
            ];
            if (isset($code)) {
                $getdata = [
                    'code' => $code
                ];
            }
            DB::beginTransaction();
            # get product details from erplay
            $products = $this->productService->getProducts($getdata, $limit, $code);
            if ($debug == 1) {
                dd($products);
            }
            if (count($products) > 0) {
                foreach ($products as $product) {

                    $count = Product::where('code', $product->code)->count();
                    if ($count > 1) {
                        echo "duplicate product found for code " . $product->code . " <br>";
                        $product->update(['roadhousePricePending' => 4]);
                        $product = $this->getduplicateProducts($product);
                    }
                    #get  details from the erply
                    echo "Product ID : " . $product->productID;
                    echo "<br>";

                    echo "Product code : " . $product->code;
                    echo "<br>";

                    $Variants =   $this->productService->getAllVariants($product->productID);
                    echo "Variants Count : " . count($Variants);
                    echo "<br>";

                    if ($product->type == 'MATRIX') {
                        if ($Variants->isNotEmpty()) {
                            foreach ($Variants as $Variant) {
                                $prices = [];
                                // $prices['special_price_withvat'] = null;
                                // $prices['normal_price_withvat'] = null;
                                // $discounts = [];
                                // $discounts['brand_discount_Per'] = null;
                                // $discounts['special_discount_per'] = null;

                                echo "Variant/Product ID : " . $Variant->productID;

                                // $prices[] = $Variant->priceWithVat;

                                # Special Price List
                                $specialPriceLists = $this->productService->getSpecialPriceList($Variant->productID);
                                if ($specialPriceLists) {
                                    foreach ($specialPriceLists as $specialPriceList) {

                                        $priceRules = $this->productService->getPriceLists($specialPriceList->pricelistID);

                                        if ($priceRules) {
                                            $ckeckAssignedToweareHouse = $this->productService->checkAssignedToWareHouse($priceRules->pricelistID);
                                            if ($debug == 2) {
                                                dd($ckeckAssignedToweareHouse);
                                            }
                                            if ($ckeckAssignedToweareHouse) {
                                                echo "Price Rules startDate : " . $priceRules->startDate . '<br>';

                                                echo "Price Rules endDate : " . $priceRules->endDate . '<br>';
                                                if (
                                                    $priceRules->startDate == '0000-00-00' ||
                                                    ($priceRules->startDate <= date('Y-m-d')  && $priceRules->endDate >= date('Y-m-d'))
                                                ) {
                                                    echo 'Processing Price List ID- ' . $specialPriceList->pricelistID . '<br>';
                                                    echo "have special price" . '<br>';
                                                    // append special price to array
                                                    $prices[] = $specialPriceList->priceWithVat;
                                                } else {
                                                    echo "no special price" . '<br>';
                                                }
                                            } else {
                                                echo "Price Rules not assigned to warehouse" . '<br>';
                                            }
                                            # dump($priceRules);
                                        } else {

                                            echo "No price rules found" . '<br>';
                                        }
                                    }
                                } else {
                                    echo "No Special Price found" . '<br>';
                                }

                                // Product Group Discount
                                if ($Variant->groupID != null || $Variant->groupID != '') {
                                    $min_group_discounted_price = $this->productService->processProductGroupDiscount($Variant);
                                    if ($min_group_discounted_price > 0) $prices[] = $min_group_discounted_price;
                                }
                        
                                // Brand Discount 
                                if ($product->brandID) {
                                    $brandDiscount = $this->productService->getBrandDiscount($product->brandID);

                                    if ($brandDiscount) {
                                        $ckeckAssignedToweareHouse = $this->productService->checkAssignedToWareHouse($brandDiscount->priceListID);
                                        if ($ckeckAssignedToweareHouse) {
                                            echo "brand discount is " . $brandDiscount->discount . "%";
                                            $priceRulesDiscount = $this->productService->getPriceLists($brandDiscount->priceListID);

                                            if ($priceRulesDiscount) {
                                                if (
                                                    $priceRulesDiscount->startDate == '0000-00-00' ||
                                                    ($priceRulesDiscount->startDate <= date('Y-m-d')  && $priceRulesDiscount->endDate >= date('Y-m-d'))
                                                ) {
                                                    $finalDiscountPrice = $Variant->priceWithVat - ($Variant->priceWithVat * ($brandDiscount->discount / 100));

                                                    $prices[] = $finalDiscountPrice;
                                                }
                                            } else {
                                                echo "no brand discount price rules found";
                                            }
                                        }
                                    } else {
                                        echo "no brand discount";
                                    }
                                } else {
                                    echo "no brand discount";
                                }
                                $isOfferPrice = 0;
                                $minPrice = 0;
                                if (count($prices) > 0) {
                                    $isOfferPrice = 1;
                                    // here we need to check the smallest value
                                    $minPrice = min($prices);
                                    

                                    echo "min price =" . $minPrice;
                                }

                                print_r($prices);
                                if ($debug == 2) {
                                    dump($minPrice);
                                    dd($prices);
                                }

                                if ($isOfferPrice == 1) {
                                }
                                $datas = [
                                    // 'priceWithTax' => $isOfferPrice == 1 ? $minPrice : $Variant->priceWithVat,
                                    // 'compareAtPrice' => $isOfferPrice == 1 ? $Variant->priceWithVat : 0,
                                    'compareAtPrice' => $minPrice,
                                    'pricePendingProcess' => 1
                                ];

                                $updateResult = $this->sourceProductService->updateSourceVariants(['sku' => $Variant->code], $datas);

                                if ($updateResult) {
                                    echo "Variant Updated =>" . $Variant->code;
                                } else {

                                    echo "Variant Not Updated";
                                }
                            }

                            $updateData = [
                                'roadhousePricePending' => 0
                            ];
                            $this->sourceProductService->updateSourceProduct(
                                ['stockId' => $product->productID],
                                ['pricePendingProcess' => 1, 'shopifyPendingProcess' => 10]
                            );
                        } else {
                            echo "no variant found";
                            $updateData = [
                                'roadhousePricePending' => 3
                            ];
                        }
                    } else {
                        $prices = [];

                        // Special Price list 
                        $special_price_lists = $this->productService->getSpecialPriceList($product->productID);
                        if ($special_price_lists->isNotEmpty()) {
                            foreach ($special_price_lists as $special_price) {
                                $price_rule = $this->productService->getPriceLists($special_price->pricelistID);
                                if ($price_rule) {
                                    $ckeckAssignedToweareHouse = $this->productService->checkAssignedToWareHouse($price_rule->pricelistID);
                                    if ($debug == 2) {
                                        dd($ckeckAssignedToweareHouse);
                                    }
                                    if ($ckeckAssignedToweareHouse) {
                                        echo "Price Rules startDate : " . $price_rule->startDate . '<br>';
                                        echo "Price Rules endDate : " . $price_rule->endDate . '<br>';
                                        if (
                                            $price_rule->startDate == '0000-00-00' ||
                                            ($price_rule->startDate <= date('Y-m-d')  && $price_rule->endDate >= date('Y-m-d'))
                                        ) {
                                            echo 'Processing Price List ID- ' . $special_price->pricelistID . '<br>';
                                            echo "have special price" . '<br>';
                                            // append special price to array
                                            $prices[] = $special_price->priceWithVat;
                                        } else {
                                            echo "No special price" . '<br>';
                                        }
                                    } else {
                                        echo "Price Rules not assigned to warehouse" . '<br>';
                                    }
                                } else {
                                    echo "No price rules found" . '<br>';
                                }
                            }
                        } else {
                            echo "No Special Price found" . '<br>';
                        }

                        // Product Group Discount
                        if ($product->groupID != null || $product->groupID != '') {
                            $min_group_discounted_price = $this->productService->processProductGroupDiscount($product);
                            if ($min_group_discounted_price > 0) $prices[] = $min_group_discounted_price;
                        }
                        
                        // Brand Discount 
                        if ($product->brandID) {
                            $brandDiscount = $this->productService->getBrandDiscount($product->brandID);

                            if ($brandDiscount) {
                                $ckeckAssignedToweareHouse = $this->productService->checkAssignedToWareHouse($brandDiscount->priceListID);
                                if ($ckeckAssignedToweareHouse) {
                                    echo "brand discount is " . $brandDiscount->discount . "%";
                                    $priceRulesDiscount = $this->productService->getPriceLists($brandDiscount->priceListID);

                                    if ($priceRulesDiscount) {
                                        if (
                                            $priceRulesDiscount->startDate == '0000-00-00' ||
                                            ($priceRulesDiscount->startDate <= date('Y-m-d')  && $priceRulesDiscount->endDate >= date('Y-m-d'))
                                        ) {
                                            $finalDiscountPrice = $product->priceWithVat - ($product->priceWithVat * ($brandDiscount->discount / 100));
                                            $prices[] = $finalDiscountPrice;
                                        }
                                    } else {
                                        echo "no brand discount price rules found";
                                    }
                                }
                            } else {
                                echo "no brand discount";
                            }
                        } else {
                            echo "no brand discount";
                        }

                        $minPrice = 0;
                        if (count($prices) > 0) {
                            // here we need to check the smallest value
                            $minPrice = min($prices);
                            echo "min price =" . $minPrice;
                        }

                        print_r($prices);
                        if ($debug == 2) {
                            dump($minPrice);
                            dd($prices);
                        }

                        $datas = [
                            // 'priceWithTax' => $isOfferPrice == 1 ? $minPrice : $product->priceWithVat,
                            // 'compareAtPrice' => $isOfferPrice == 1 ? $product->priceWithVat : 0,
                            'compareAtPrice' => $minPrice,
                            'pricePendingProcess' => 1,
                            'shopifyPendingProcess' => 1
                        ];

                        $updateResult = $this->sourceProductService->updateSourceProduct(['stockId' => $product->productID], $datas);

                        if ($updateResult) {
                            echo "Product Updated =>" . $product->code;
                            $updateData = [
                                'roadhousePricePending' => 0
                            ];
                        } else {
                            echo "Product Not Updated";
                            $updateData = [
                                'roadhousePricePending' => 1
                            ];
                        }
                    }

                    if (isset($updateData) && !empty($updateData)) {
                        $this->productService->updateProducts($product->productID, $updateData);
                    }

                    echo "Procerss Completed for Product ID : " . $product->productID;
                }

                echo "Whole Process Completed";
            } else {
                echo "no shopifyPricePending products found";
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            return $e->getMessage();
        }
    }

    private function getDiscountPrice($price, $discount)
    {
        return round($price - ($price * $discount) / 100, 2);
    }

    public function getduplicateProducts($product)
    {

        return Product::query()
            ->where('code', $product->code)
            ->orderBy('lastModified', 'desc')
            ->first();
    }
}
