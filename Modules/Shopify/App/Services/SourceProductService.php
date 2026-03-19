<?php

namespace Modules\Shopify\App\Services;

use Modules\Shopify\App\Models\Source\SourceImage;
use Modules\Shopify\App\Models\Source\SourceProduct;
use Modules\Shopify\App\Models\source\SourceVariant;

class SourceProductService
{
    public function getAllProduct($limit = 1)
    {
        $sql = SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    // ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where([
            'status' => 1,
            'shopifyPendingProcess' => 1
        ]);
        if (\request()->query('order') == 'DESC') {
            $sql->orderby('lastPushedDate', 'DESC');
        } else {
            $sql->orderby('lastPushedDate', 'ASC');
        }
        return $sql->limit($limit)->get();
    }

    public function getAllVariationProducts($limit)
    {
        return SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where([
            'shopifyPendingProcess' => 0,
            'shopifyVariationProcess' => 1
        ])
        ->orderby('lastPushedDate', 'ASC')
        ->limit($limit)
        ->get();
    }

    public function getPendingProduct($condition, $limit = 1)
    {

        return SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where($condition)
            ->orderby('lastSyncDate', 'ASC')
            ->limit($limit)
            ->get();
    }
    public function getPendingProductAppend($condition, $limit = 1)
    {
        if (request()->query('order') == 'DESC') {
            $order = 'DESC';
        } else {
            $order = 'ASC';
        }
        return SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where($condition)
        ->where(function ($q){
            $q->where('imagePendingProcess', 0)
            ->orWhere('imagePendingProcess', 3);
        })
        ->orderBy('lastSyncDate', $order)
        ->limit($limit)
        ->get();
    }

    public function getSohPendingProduct($condition, $limit = 1)
    {
        $sql = SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                
                    // ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])
        ->where($condition)->where('shopifyProductId', '!=', null);

        if (\request()->query('order') == 'ASC') {
            $orderby = 'ASC';
        } else {
            $orderby = 'DESC';
        }
        return $sql->orderby('lastSyncDate', $orderby)
            ->limit($limit)
            ->get();
    }

    public function getPricePendingProduct($condition, $limit = 1)
    {
        return SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where($condition)->where('shopifyProductId', '!=', null)
            ->orderby('lastSyncDate', 'ASC')
            ->limit($limit)
            ->get();
    }
    
    public function getImagePendingProduct($condition, $limit)
    {

        return SourceProduct::with([
            'images' => function ($query) {
                $query->where('isDeleted', '=', 0)
                    ->distinct('name')
                    ->orderBy('colorID', 'asc')
                    ->orderBy('order', 'asc');
            }
        ])->where($condition)->where('shopifyProductId', '!=', null)
            ->orderby('lastPushedDate', 'ASC')
            ->limit($limit)
            ->get();
    }


    public function getProduct($code)
    {
        return SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    // ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where('handle', $code)->get();
    }

    public function getProductVariation($code)
    {
        return SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    // ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where('handle', $code)->get();
    }

    public function getAllVariationPending($limit = 1)
    {
        return SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    // ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')->orderBy('sizeOrder');
            }
        ])->where([
            'shopifyVariationProcess' => 1
        ])
        ->orderby('lastPushedDate', 'ASC')
        ->limit($limit)
        ->get();
    }

    public function getPreviousDeletedVariantProducts($sku = null, $limit = 1) 
    {
        $sql = SourceProduct::whereHas('variants', function ($query) {
            $query->where('shopifyPendingProcess', 1)
                ->where('priceWithTax', '>', 0)
                ->where('isManualDeleted', 1);
            });

        if ($sku) $sql->where('sku', $sku);
        return $sql->orderby('lastPushedDate', 'ASC')->limit($limit)->get();
    }

    #update by id
    public function updateProduct($id, $data)
    {
        return SourceProduct::where('id', $id)->update($data);
    }

    public function updateVariants($sku, $data)
    {
        return SourceVariant::where('sku', $sku)->update($data);
    }

    public function updateImage($condition, $data)
    {
        return SourceImage::where($condition)->update($data);
    }

    public function getProductsV1($code = null, $limit = null)
    {
        $query = SourceProduct::with([
            'variants' => function ($query) {
                $query->where('shopifyPendingProcess', 1)
                    // ->where('priceWithTax', '>', 0)
                    ->orderBy('colorOrder')
                    ->orderBy('sizeOrder');
            }
        ])->where(function($query) {
            $query->where('shopifyPendingProcess', 0)
            ->orWhere('shopifyPendingProcess', 4); // Newly Created 
        });

        if ($code != null) {
            $query->where('handle', $code);
        } else {
            $query->where('shopifyVariationProcess', 1);
        }

        if (\request()->query('order') == 'DESC') {
            $orderby = 'DESC';
        } else {
            $orderby = 'ASC';
        }
        $query->orderby('lastSyncDate', $orderby);

        if ($limit != null) {
            $query->limit($limit);
        }
        return $query->get()
        ->map(function ($product) {
            // only keep variants if isMatrix = 1
            if ($product->isMatrix != 1) {
                $product->setRelation('variants', collect());
            }
            return $product;
        });
    }


    public function getDefaultVariant($shopify_product_id)
    {
        $query = ' {
            product(id: "' . $shopify_product_id . '") {
                id
                title
                variants(first: 1) {
                    edges {
                        node {
                            id
                            title
                            inventoryItem {
                                id
                            }
                        }
                    }
                }
            }
        }';
        return $query;
    }
}
