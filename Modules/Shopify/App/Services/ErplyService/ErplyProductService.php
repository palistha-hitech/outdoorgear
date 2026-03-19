<?php

namespace Modules\Shopify\App\Services\ErplyService;

use Exception;
use Modules\Shopify\App\Models\ErplyModel\BrandDiscountPrice;
use Modules\Shopify\App\Models\ErplyModel\Category;
use Modules\Shopify\App\Models\ErplyModel\ErplySoh;
use Modules\Shopify\App\Models\ErplyModel\Images;
use Modules\Shopify\App\Models\ErplyModel\Order;
use Modules\Shopify\App\Models\ErplyModel\PriceList;
use Modules\Shopify\App\Models\ErplyModel\Product;
use Modules\Shopify\App\Models\ErplyModel\SpecialPriceList;
use Modules\Shopify\App\Models\ErplyModel\Variant;
use Modules\Shopify\App\Models\ErplyModel\WareHouseLocation;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Models\Source\SourceVariant;

class ErplyProductService
{
    public function getProducts($whereCondition, $limit, $code)
    {
        try {
                //  $products =Product::with(['variants' => function ($query) {
            $products = SourceProduct::with(['variants' => function ($query) {
                $query->orderBy('colorOrder')->orderBy('sizeOrder');
            }])->when($code != '', function ($query) use ($code) {
                $query->where('code', $code);
            })->when($code == '', function ($query) use ($whereCondition) {
                $query->where($whereCondition);
            })->orderBy('lastModified', 'desc')->limit($limit)->get();

            return $products;
        } catch (Exception $th) {
            return $th->getMessage();
        }
    }
    public function getImagesProducts($whereCondition, $limit)
    {
        $products = Product::with(['images' => function ($query) {
            $query->orderBy('order', 'asc');
        }])->where($whereCondition)->limit($limit)->get();

        return $products;
    }
    public  function updateProducts($id, $datas)
    {
        return Product::where('productID', $id)->update($datas);
    }

    public function getCategory()
    {
        $sql = Category::query();

        if (\request()->query('categoryid')) {
            $sql->where('productCategoryID', \request()->query('categoryid'));
        } else {
            $sql->where([
                'roadhouseStatus' => 1
            ]);
        }

        if (\request()->query('limit')) {
            $sql->take(\request()->query('limit'));
        } else {
            $sql->take(100);
        }

        return $sql->get();
    }

    public  function updateCategory($id, $datas)
    {
        return Category::where('id', $id)->limit(2)->update($datas);
    }

    public function getLocations()
    {
        return WareHouseLocation::all();
    }


    public function getImages($productID)
    {
        return Images::query()
            ->selectRaw('ANY_VALUE(id), colourID')
            ->where('parentProductID', $productID)
            ->groupBy('colourID')
            ->get();
    }



    public function getVariants($productID)
    {
        return SourceVariant::where('productID', $productID)->first();
    }

    public function getAllVariants($productID)
    {
            //  return Variant::where('parentProductID', $productID)->get();
        return SourceVariant::where('product_id', $productID)->get();
    }

    public function getSoh($whereCondition)
    {
        return ErplySoh::where($whereCondition)->get();
    }
    // public function getVariantSoh($productID)
    // {
    //     return ErplySoh::where('erplyProductID', $productID)->get();
    // }
        public function getVariantSoh($productID)
    {
        return SourceVariant::where('product_id', $productID)->get();
    }

    public function getPriceLists($pricelistID)
    {
        return PriceList::where([
            'pricelistID' => $pricelistID,
            'active' => 1,
            'isDeleted' => 0
        ])->first();
    }
    public function checkAssignedToWareHouse($pricelistID)
    { // check all column of pricelists
        return WareHouseLocation::where('warehouseID', 8)
            ->where(function ($q) use ($pricelistID) {
                $q->where('priceListID', $pricelistID)
                    ->orWhere('priceListID2', $pricelistID)
                    ->orWhere('priceListID3', $pricelistID)
                    ->orWhere('priceListID4', $pricelistID)
                    ->orWhere('priceListID5', $pricelistID);
            })
            ->first();
    }
    public function getSpecialPriceList($productID)
    {
        return SpecialPriceList::where([
            'productID' => $productID,
            'isDeleted' => 0
        ])->get();
    }

    public function getBrandDiscount($brandID)
    {
        return BrandDiscountPrice::where([
            'brandID' => $brandID,
            'isDeleted' => 0
        ])->where('discount', '>', 0)->first();
    }

    public function getOrdersDetails($orderby, $order)
    {
        return Order::orderBy($order, $orderby)->get();
    }

    public function getSpecialPriceListGroups($groupID)
    {
        return SpecialPriceList::where([
            'productID' => $groupID,
            'type' => 'PRODGROUP',
            'isDeleted' => 0
        ])->get();
    }

    public function processProductGroupDiscount($product)
    {
        $discount_price_array = [];
        $group_discount_list = $this->getSpecialPriceListGroups($product->groupID);
        if ($group_discount_list->isNotEmpty()) {
            foreach ($group_discount_list as $row) {
                $price_rule = $this->getPriceLists($row->pricelistID);
                if ($price_rule) {
                    $ckeckAssignedToweareHouse = $this->checkAssignedToWareHouse($price_rule->pricelistID);
                    // dump($ckeckAssignedToweareHouse);

                    if ($ckeckAssignedToweareHouse) {
                        echo "Product Group discount is " . $row->discountPercent . "%";

                        if (
                            $price_rule->startDate == '0000-00-00' ||
                            ($price_rule->startDate <= date('Y-m-d')  && $price_rule->endDate >= date('Y-m-d'))
                        ) {
                            echo 'Processing Product Group Price List ID - ' . $row->pricelistID . '<br>';
                            echo "have special price" . '<br>';

                            $finalDiscountPrice = $product->priceWithVat - ($product->priceWithVat * ($row->discountPercent / 100));
                            $discount_price_array[] = $finalDiscountPrice;

                        } else {
                            echo "No product group price" . '<br>';
                        }
                    } else {
                        echo "Product Group Price Rules not assigned to warehouse" . '<br>';
                    }
                } else {
                    echo "No product group price rules found" . '<br>';
                }
            }
        } else {
            echo "No Product Group Discount found" . '<br>';
        }

        $minPrice = 0;
        if (count($discount_price_array) > 0) {
            $minPrice = min($discount_price_array);
            echo "Min product group discount price = " . $minPrice;
        }
        return $minPrice;
    }
}