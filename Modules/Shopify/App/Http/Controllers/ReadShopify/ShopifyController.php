<?php

namespace Modules\Shopify\App\Http\Controllers\ReadShopify;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Modules\Shopify\App\Models\ShopifyCursor;
use Modules\Shopify\App\Models\Source\ShopifyOrderRefund;
use Modules\Shopify\App\Traits\ShopifyTrait;

class ShopifyController extends Controller
{

    use ShopifyTrait;

    protected $live = 1;
    protected $clientCode;

    public function __construct()
    {
        $this->clientCode = $this->getClientCode();
    }

    public function getRefund(Request $req)
    {

        // $this->setEnv($req);
        $debug = 0;
        if (isset($req->debug)) {
            $debug = $req->debug;
        }
        //first getting cursor
        $query = $this->getRefundQuery($this->clientCode, $this->live);
        if ($debug == 1) {
            dd($query);
        }
        $datas = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);
        if ($debug == 2) {
            dd($datas);
        }

        if (!empty($datas->data->orders->edges)) {
            foreach (@$datas->data->orders->edges as $mp) {
                $details = $mp->node;
                $newsystemOrderId = str_replace('gid://shopify/Order/', '', $details->id);
                $newsystemRefundId = str_replace('gid://shopify/Refund/', '', $details->refunds[0]->id);
                $refund_product_id = [];
                $refund_product_code = [];
                $refund_product_price = [];
                $refund_product_qty = [];
                foreach ($details->refunds[0]->refundLineItems->edges as $lineItem) {
                    $refundProductId = str_replace('gid://shopify/LineItem/', '', $lineItem->node->lineItem->id);
                    $refund_product_id[] = $refundProductId;
                    $refund_product_code[] = $lineItem->node->lineItem->sku;
                    $refund_product_price[] = $lineItem->node->priceSet->shopMoney->amount;
                    $refund_product_qty[] = $lineItem->node->quantity;
                }

                $refundData = [
                    'shopifyRefundString' => $details->refunds[0]->id,
                    'newsystemRefundId' => $newsystemRefundId,
                    'newsystemOrderId' => $newsystemOrderId,
                    'newSystemOrderNumber' => $details->name,
                    'refund_amount' => $details->refunds[0]->totalRefundedSet->shopMoney->amount,
                    'refund_product_id' => isset($refund_product_id) ? implode(',', $refund_product_id) : '',
                    'refund_product_code' => isset($refund_product_code) ? implode(',', $refund_product_code) : '',
                    'refund_product_price' => isset($refund_product_price) ? implode(',', $refund_product_price) : '',
                    'refund_product_qty' => isset($refund_product_qty) ? implode(',', $refund_product_qty) : '',
                    'refund_shipping_amount' => $details->shippingLine->originalPriceSet->shopMoney->amount ?? 0,
                    'orderUpdateDate' => date('Y-m-d H:i:s', strtotime($details->updatedAt)),
                    'shopifyRetrievedTime' => date('Y-m-d H:i:s', strtotime($details->createdAt)),
                    'refund_created_exact' => $details->updatedAt,
                ];
                if ($debug == 3) {
                }
                ShopifyOrderRefund::updateOrcreate(
                    [
                        'newsystemRefundId' => $newsystemRefundId,
                    ],
                    $refundData
                );
            }
            /** update or create cursor */
            if (@$datas->data->orders->pageInfo->endCursor) {
                $lastCursor = @$datas->data->orders->pageInfo->endCursor;
                ShopifyCursor::updateOrcreate(
                    [
                        'clientCode' => $this->clientCode,
                        'cursorName' => 'refund',
                        'isLive' => $this->live,
                    ],
                    [
                        'clientCode' => $this->clientCode,
                        'cursorName' => 'refund',
                        'cursor' => $lastCursor,
                        'isLive' => $this->live,
                    ]
                );
            }

            dd(@$datas);
        } else {
            return response('No Orders Found to Sync');
        }
    }
}