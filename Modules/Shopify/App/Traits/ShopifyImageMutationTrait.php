<?php

namespace Modules\Shopify\App\Traits;

use Exception;
use Modules\Shopify\App\Models\Source\SourceImage;

trait ShopifyImageMutationTrait
{
    use ShopifyProductMutationTrait, ShopifyTrait;

    public function __construct() {}

    public function VariantAppendMedia($product, $productId)
    {
        $variantDataMutation = 'mutation {';
        $variantDataMutation .= 'productVariantAppendMedia(';
        $variantDataMutation .= 'productId: "' . $productId . '",';

        $variantDataMutation .= 'variantMedia: [';
        foreach ($product->variants as $variant) {
            if ($variant->shopifyVariantId == null || $variant->shopifyVariantId == '') {
                continue;
            }
            if ($variant->displayInWebshop == 0 || $variant->status == 'ARCHIVED') continue;
            if (count($variant->images) > 0) {
                $variantDataMutation .= '{
                mediaIds: [';

                $variantDataMutation .= '"' . $variant->images->first()->shopifyMediaId . '",';

                $variantDataMutation .= '],';
                $variantDataMutation .= '   variantId:"' . $variant->shopifyVariantId . '"
             },';
            } else {
                $mainImage = SourceImage::where([
                    'product_id' => $variant->product_id,
                    'isDeleted' => 0,
                    'colorID' => $variant->colorID
                ])->whereNotNull('shopifyMediaId')->first();
                // if (!$mainImage) {
                //     $mainImage = SourceImage::where([
                //         'product_id' => $variant->product_id,
                //         'isDeleted' => 0
                //     ])->whereNotNull('shopifyMediaId')->first();
                // }
                if ($mainImage) {
                    $variantDataMutation .= '{
                        mediaIds: [';

                    $variantDataMutation .= '"' . $mainImage->shopifyMediaId . '",';

                    $variantDataMutation .= '],';
                    $variantDataMutation .= '   variantId:"' . $variant->shopifyVariantId . '"
                     },';
                }
            }
        }
        $variantDataMutation .= ']';

        $variantDataMutation .= ') {
            productVariants {
                id
                image{
                    id
                    url
                }
            }';
        $variantDataMutation .= 'userErrors { field message }';
        $variantDataMutation .= '}';
        $variantDataMutation .= '}';

        return $variantDataMutation;
    }

    public function getMediaByHandle($handle)
    {
        try {
            # $handle = $this->filterHandle($handle);

            dump('handle :', $handle);
            $query = '{
            product(id: "' . $handle . '") {
              id
              media(first: 100) {
                edges {
                  node {
                    id
                    alt
                  }
                }
              }
            }
          }';
            $response = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
            dump($response);
            if (isset($response->data->product->id)) {
                dump('no error');
                return [
                    'status' => 1,
                    'pid' => $response->data->product->id,
                    'mediaIds' => $response->data->product->media->edges,
                ];
            } else {
                dump('error');
                return [
                    'status' => 0,
                    'error' => $response,
                ];
            }

        } catch (Exception $e) {
            dd($e);

            return [
                'status' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
    public function deleteImageMutation($mediaIds, $productId)
    {
        try {

            $mutation = 'mutation {';
            $mutation .= 'productDeleteMedia(';
            $mutation .= 'mediaIds:[';
            foreach ($mediaIds as $mediaId) {
                $mutation .= '"' . $mediaId->node->id . '",';
            }

            $mutation .= ' ],';
            $mutation .= 'productId:"' . $productId . '"';
            $mutation .= '){
                    deletedMediaIds
                    deletedProductImageIds
                    product {
                    id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }';
            $response = $this->sendShopifyQueryRequestV2('POST', $mutation, $this->live);

            if (@$response->data->productDeleteMedia->deletedMediaIds && count($response->data->productDeleteMedia->deletedMediaIds) > 0) {

                return [
                    'status' => 1,
                    'deletedMediaIds' => $response->data->productDeleteMedia->deletedMediaIds
                ];
            }
            return [
                'status' => 0,
                'deletedMediaIds' => $response
            ];
        } catch (Exception $e) {
            dd($e);
            return [
                'status' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getImagesSrc($image)
    {
        if (isset($image->name)) {
            $src = "https://cdn.erply.com/images/603965/" . $image->name;
            $mutation = '{
                  alt:"' . $image->name . '",
                  mediaContentType:IMAGE,
                  originalSource:"' . $src . '"
              },';
            return $mutation;
        }
        return false;
    }
    public function productCreateMedia($images, $ShopifyProductId)
    {

        try {
            $mutation = 'mutation {
                productCreateMedia(media:[';
            if (count($images) <= 0) {
                return [
                    'status' => 3,
                    'error' => 'No images found'
                ];
            }
            foreach ($images as $key => $image) {
                if ($key == 0) {
                    foreach ($image as $img) {
                        $mutation .= $this->getImagesSrc($img);
                    }
                } else {
                    $mutation .= $this->getImagesSrc($image[0]);
                }
            }

            $mutation .= '], productId:"' . $ShopifyProductId . '") {
                media {

                      id
                      alt

                }
                mediaUserErrors {
                  message
                }
                product {
                  id
                }
                userErrors {
                  field
                  message
                }
              }
              }';

            print_r($mutation);
            $response = $this->sendShopifyQueryRequestV2('POST', $mutation, $this->live);
            print_r($response);
            if (count($response->data->productCreateMedia->media) > 0) {
                return [
                    'status' => 1,
                    'mediaCreated' => $response->data->productCreateMedia->media
                ];
            }
            return [
                'status' => 0,
                'error' => $response
            ];
        } catch (Exception $e) {
            dd($e);
            return [
                'status' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
