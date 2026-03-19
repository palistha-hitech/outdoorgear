<?php

namespace Modules\Shopify\App\Services;

use Illuminate\Support\Facades\Log;
use Modules\Shopify\App\Models\ShopifyCursor;
use Modules\Shopify\App\Models\Source\SourceOrder;
use Modules\Shopify\App\Models\Source\SourceOrderDelivery;
use Modules\Shopify\App\Models\Source\SourceOrderProduct;

class ShopifyOrderService
{
    protected $clientCode;

    protected $live;

    public function __construct($clientCode, $live = false)
    {
        $this->clientCode = $clientCode;
        $this->live = $live;
    }

    public function processOrders($res)
    {
        if (! empty($res->data->orders->edges)) {
            foreach ($res->data->orders->edges as $orderEdge) {
                $orderNode = $orderEdge->node;
                $shopifyOrderStringId = $orderNode->id;
                $shopifyOrderId = last(explode('/', $orderNode->id));
                //Calculate highest risk level
                $riskLevels = collect($orderNode->risks)->pluck('level');
                $highestRiskLevel = $riskLevels->contains('HIGH') ? 'HIGH' : ($riskLevels->contains('MEDIUM') ? 'MEDIUM' : 'LOW');
                $sourceOrder = SourceOrder::updateOrCreate(
                    ['shopify_order_id' => $shopifyOrderId],
                    [
                        'shopify_order_string_id' => $shopifyOrderStringId,
                        'shpify_order_number' => $orderNode->name,
                        'total_order' => $orderNode->totalPriceSet->shopMoney->amount,
                        'subtotal_order' => $orderNode->subtotalPriceSet->shopMoney->amount,
                        'tax_amount' => $orderNode->totalTaxSet->shopMoney->amount,
                        'total_shipping' => $orderNode->shippingLine->originalPriceSet->shopMoney->amount ?? '0.0',
                        'currency' => $orderNode->currencyCode,
                        'order_created' => $orderNode->createdAt,
                        'order_completed' => $orderNode->updatedAt,
                        'fullfillment_status' => $orderNode->displayFulfillmentStatus,
                        'note' => $orderNode->note,
                        'shipping_method' => $orderNode->shippingLine->code,
                        'risk_level' => $highestRiskLevel,
                        'total_items' => collect($orderNode->lineItems->edges)->sum(fn ($edge) => $edge->node->quantity),
                        'coupon_code' => collect($orderNode->discountCodes)->pluck('code')->implode(','),
                        'coupon_amount' => $orderNode->totalDiscountsSet->shopMoney->amount,
                    ]
                );
                //@ Now, process each line item for the order
                foreach ($orderNode->lineItems->edges as $edge) {
                    $node = $edge->node;
                    $variant = $node->variant ?? null;
                    $color = $size = null;
                    $shopifyProductStringId = $node->id;
                    $shopifyProductId = last(explode('/', $node->id));
                    //Extract color and size if variant exists and has selectedOptions
                    if ($variant && ! empty($variant->selectedOptions)) {
                        foreach ($variant->selectedOptions as $option) {
                            if ($option->name === 'Color') {
                                $color = $option->value;
                            } elseif ($option->name === 'Size') {
                                $size = $option->value;
                            }
                        }
                    }
                    //Create or update the line item
                    SourceOrderProduct::updateOrCreate(
                        ['shopify_product_id' => $shopifyProductId],
                        [
                            'shopify_product_string_id' => $shopifyProductStringId,
                            'shopify_order_id' => $shopifyOrderId,
                            'product_sku' => $node->sku,
                            'quantity' => $node->quantity,
                            'discount_amount' => 0,
                            'total_discount' => $node->totalDiscountSet->shopMoney->amount ?? '0.0',
                            'variant_sku' => $variant->sku ?? null,
                            'variant_title' => $variant->title ?? null,
                            'variant_price' => $variant->price ?? null,
                            'color' => $color,
                            'size' => $size,
                        ]
                    );
                }
                // Process customer and address details
                $customer = $orderNode->customer;
                $billingAddress = $orderNode->billingAddress;
                $shippingAddress = $orderNode->shippingAddress;
                $shopifyCustomerStringId = $customer->id;
                SourceOrderDelivery::updateOrCreate(
                    [
                        'shopify_customer_id' => last(explode('/', $customer->id)),
                        'shopify_order_id' => $shopifyOrderId,
                    ],
                    [
                        'shopify_customer_string_id' => $shopifyCustomerStringId,
                        'first_name' => $customer->firstName,
                        'last_name' => $customer->lastName,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'address1' => $customer->defaultAddress->address1,
                        'address2' => $customer->defaultAddress->address2,
                        'city' => $customer->defaultAddress->city,
                        'province' => $customer->defaultAddress->province,
                        'country' => $customer->defaultAddress->country,
                        'zip' => $customer->defaultAddress->zip,
                        'defalut_phone' => $customer->defaultAddress->phone,
                        'billing_address_first_name' => $billingAddress->firstName,
                        'billing_address_last_name' => $billingAddress->lastName,
                        'billing_address_email' => $customer->email,
                        'billing_address_city' => $billingAddress->city,
                        'billing_address_province' => $billingAddress->province,
                        'billing_address_country' => $billingAddress->country,
                        'billing_address_zip' => $billingAddress->zip,
                        'billing_address_phone' => $billingAddress->phone,
                        'shipping_address_first_name' => $shippingAddress->firstName,
                        'shipping_address_last_name' => $shippingAddress->lastName,
                        'shipping_address_email' => $customer->email,
                        'shipping_address_phone' => $shippingAddress->phone,
                        'shipping_address_city' => $shippingAddress->city,
                        'shipping_address_province' => $shippingAddress->province,
                        'shipping_address_country' => $shippingAddress->country,
                        'shipping_address_zip' => $shippingAddress->zip,
                    ]
                );
            }
            if (@$res->data->orders->pageInfo->endCursor) {
                $lastCursor = @$res->data->orders->pageInfo->endCursor;
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
        } else {
            Log::info('No Orders Found to Sync');
        }
    }
}
