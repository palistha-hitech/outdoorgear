<?php

namespace Modules\Shopify\App\Http\Controllers\ReadShopify;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use App\Http\Controllers\Controller;
use DateTime;
use Modules\Shopify\App\Models\ShopifyCursor;
use Modules\Shopify\App\Models\ErplyModel\{Customer, Order, OrderDelivery, OrderProduct, Refund};
use Modules\Shopify\App\Models\Source\{SourceOrder, SourceOrderDelivery, SourceOrderProduct, SourceRefund, SourceRefundLineItem};
use Modules\Shopify\App\Traits\ShopifyTrait;

class OrderController extends Controller
{
    use ShopifyTrait;

    protected $live = 1;

    protected $clientCode;

    public function __construct()
    {
        $this->clientCode = $this->getClientCode();
    }

    public function getOrders(Request $request)
    {
        $cursorName = $request->cursorName ?? 'order';
        $debug = 0;
        $orderId = $request->orderId;
        $limit = $request->limit ?? 3;
        if ($orderId) {
            $query = $this->getSingleOrdersQuery($orderId);
        } else {
            $query = $this->getOrdersQuery($this->clientCode, $this->live,  $cursorName, $limit);
        }
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
        if (!empty($res->data->orders->edges)) {
            DB::beginTransaction();
            try {
                $lastUpdatedDate = '';
                foreach ($res->data->orders->edges as $orderEdge) {
                    $orderNode = $orderEdge->node;
                    $cursor = $orderEdge->cursor;

                    Log::info('orders: ' . json_encode($orderNode));
                    $shopifyOrderStringId = $orderNode->id;
                    $shopifyOrderId = last(explode('/', $orderNode->id));
                    $riskLevels = collect($orderNode->risks)->pluck('level');
                    $highestRiskLevel = $riskLevels->contains('HIGH') ? 'HIGH' : ($riskLevels->contains('MEDIUM') ? 'MEDIUM' : 'LOW');
                    $deliveryMethod = $orderNode->shippingLine->code;

                    $sourceOrder = SourceOrder::updateOrCreate(
                        ['shopify_order_id' => strval($shopifyOrderId)],
                        [
                            'shopify_order_string_id' => $shopifyOrderStringId,
                            'shpify_order_number' => $orderNode->name,
                            'total_order' => $orderNode->totalPriceSet->shopMoney->amount,
                            'subtotal_order' => $orderNode->subtotalPriceSet->shopMoney->amount,
                            'tax_amount' => @$orderNode->totalTaxSet->shopMoney->amount,
                            'total_shipping' => @$orderNode->shippingLine->originalPriceSet->shopMoney->amount ?? '0.0',
                            'currency' => @$orderNode->currencyCode,
                            'order_created' => @$orderNode->createdAt,
                            'order_completed' => @$orderNode->updatedAt,
                            'fullfillment_status' => @$orderNode->displayFulfillmentStatus,
                            'note' => @$orderNode->note,
                            'shipping_method' => @$orderNode->shippingLine->code,
                            'risk_level' => @$highestRiskLevel,
                            'total_items' => @collect($orderNode->lineItems->edges)->sum(fn($edge) => $edge->node->quantity),
                            'coupon_code' => @collect($orderNode->discountCodes)->pluck('code')->implode(','),
                            'coupon_amount' => @$orderNode->totalDiscountsSet->shopMoney->amount,
                            'payment_method' => @$orderNode->paymentGatewayNames[0],
                            'payment_detail_status' => @$orderNode->displayFinancialStatus,
                            'shiopifyCursor' => $cursor,
                        ]
                    );
                    $this->processShopifyOrder($sourceOrder, $cursor, $orderNode);
                    //@ Now, line item for the order
                    foreach ($orderNode->lineItems->edges as $edge) {
                        $node = $edge->node;
                        $variant = $node->variant ?? null;
                        $color = $size = null;
                        $shopifyProductStringId = $node->id;
                        $shopifyProductId = last(explode('/', $node->id));
                        //Extract color and size if variant exists and has selectedOptions
                        if ($variant && !empty($variant->selectedOptions)) {
                            foreach ($variant->selectedOptions as $option) {
                                if ($option->name === 'Color') {
                                    $color = $option->value;
                                } elseif ($option->name === 'Size') {
                                    $size = $option->value;
                                }
                            }
                        }
                        //Create or update the line item
                        $sourceProduct = SourceOrderProduct::updateOrCreate(
                            ['shopify_product_id' => strval($shopifyProductId)],
                            [
                                'shopify_product_string_id' => @$shopifyProductStringId,
                                'shopify_order_id' => @$shopifyOrderId,
                                'product_sku' => @$node->sku,
                                'quantity' => @$node->quantity,
                                'discount_amount' => 0,
                                'total_discount' => $node->totalDiscountSet->shopMoney->amount ?? '0.0',
                                'variant_sku' => @$variant->sku ?? null,
                                'variant_title' => @$variant->title ?? null,
                                'variant_price' => @$variant->price ?? null,
                                'color' => @$color,
                                'size' => @$size,
                            ]
                        );
                        $this->processShopifyProduct($sourceProduct, $shopifyOrderStringId);
                    }
                    // Process customer and address details
                    $customerDetails = $orderNode->customer;
                    $billingAddress = $orderNode->billingAddress;
                    $shippingAddress = $orderNode->shippingAddress;
                    $shopifyCustomerStringId = $customerDetails->id;

                    $sourceCustomer = SourceOrderDelivery::updateOrCreate(
                        [
                            'shopify_customer_id' => strval(last(explode('/', $customerDetails->id))),
                            'shopify_order_id' => strval($shopifyOrderId),
                        ],
                        [
                            'shopify_customer_string_id' => $shopifyCustomerStringId,
                            'first_name' => @$customerDetails->firstName ?? null,
                            'last_name' => @$customerDetails->lastName,
                            'email' => @$customerDetails->email,
                            'phone' => @$customerDetails->phone,
                            'address1' => @$customerDetails->defaultAddress->address1,
                            'address2' => @$customerDetails->defaultAddress->address2,
                            'city' => @$customerDetails->defaultAddress->city,
                            'province' => @$customerDetails->defaultAddress->province,
                            'country' => @$customerDetails->defaultAddress->country,
                            'zip' => @$customerDetails->defaultAddress->zip,
                            'defalut_phone' => @$customerDetails->defaultAddress->phone,
                            'billing_address_first_name' => @$billingAddress->firstName,
                            'billing_address_last_name' => @$billingAddress->lastName,
                            'billing_address_email' => @$customerDetails->email,
                            'billing_address_city' => @$billingAddress->city,
                            'billing_address_province' => @$billingAddress->province,
                            'billing_address_country' => @$billingAddress->country,
                            'billing_address_zip' => @$billingAddress->zip,
                            'billing_address_phone' => @$billingAddress->phone,
                            'shipping_address_first_name' => @$shippingAddress->firstName ?? null,
                            'shipping_address_last_name' => @$shippingAddress->lastName ?? null,
                            'shipping_address_email' => @$customerDetails->email ?? null,
                            'shipping_address_phone' => @$shippingAddress->phone ?? null,
                            'shipping_address_city' => @$shippingAddress->city ?? null,
                            'shipping_address_province' => @$shippingAddress->province ?? null,
                            'shipping_address_country' => @$shippingAddress->country ?? null,
                            'shipping_address_zip' => @$shippingAddress->zip ?? null,
                        ]
                    );
                    $this->processShopifyCustomer($customerDetails, $shippingAddress, $billingAddress, $shopifyOrderStringId, $deliveryMethod, $debug, $sourceCustomer);
                    $lastUpdatedDate = $orderNode->updatedAt;

                    // Update or create the cursor for the `updatedAt` value

                }

                if (@$res->data->orders->pageInfo->endCursor) {
                    $lastCursor = @$res->data->orders->pageInfo->endCursor;
                    ShopifyCursor::updateOrcreate(
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'order',
                            'isLive' => $this->live,
                        ],
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'order',
                            'cursor' => $lastCursor,
                            'isLive' => $this->live,
                        ]
                    );
                    Log::info("Extracted cursor: {$lastCursor}");
                    DB::commit();
                }
                if ($lastUpdatedDate) {
                    dump($lastUpdatedDate);
                    $cursorByLMD = ShopifyCursor::updateOrCreate(
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'orderBYLMD',
                            'isLive' => $this->live,
                        ],
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'orderBYLMD',
                            'cursor' => $lastUpdatedDate,
                            'isLive' => $this->live,
                        ]
                    );

                    DB::commit();
                }
            } catch (\Exception $e) {
                DB::rollBack();

                throw $e;
            }
        } else {
            return response('No Orders Found to Sync');
        }
    }

    private function processShopifyOrder($sourceOrder, $cursor, $node)
    {
        date_default_timezone_set('Australia/Melbourne');
        try {
            $orderCreated = Order::UpdateOrcreate(
                ['newSystemOrderID' => $sourceOrder->shopify_order_string_id],
                [
                    'newSystemOrderID' => $sourceOrder->shopify_order_string_id,
                    'newSystemOrderNumber' => $sourceOrder->shpify_order_number,
                    'newSystemCustomerID' => $node->customer->id,
                    'order_created' => date('Y-m-d H:i:s', strtotime($node->createdAt)), //(new DateTime($sourceOrder->order_created))->format('Y-m-d H:i:s'),
                    'newSystemCustomerEmail' => $node->customer->email,
                    'order_completed' => date('Y-m-d H:i:s', strtotime($node->updatedAt)), // (new DateTime($sourceOrder->order_completed))->format('Y-m-d H:i:s'),
                    'currency' => $sourceOrder->currency,
                    'order_total' => $sourceOrder->total_order,
                    'order_subtotal' => $sourceOrder->subtotal_order,
                    'total_items' => $sourceOrder->total_items,
                    'shipping_methods' => $sourceOrder->shipping_method,
                    'total_shipping' => $sourceOrder->total_shipping,
                    'payment_detail_title' => $sourceOrder->payment_method,
                    'payment_detail_status' => @$node->displayFinancialStatus ?? null,
                    'note' => $sourceOrder->note ?? 'Shopify',
                    'taxAmount' => $sourceOrder->tax_amount,
                    'coupon_code' => $sourceOrder->coupon_code,
                    'coupon_amount' => $sourceOrder->coupon_amount,
                    'internalPaymentType' => $sourceOrder->payment_method,
                    'fullfillment_status' => $sourceOrder->fullfillment_status,
                    'shopifyCursor' => $cursor,
                    'invoiceSyncCount' => 0,
                    'orderExported' => 0,
                    'cacOrderID' => '',
                    'authorizationCode' => '',
                    'updatedAuthorizationCode' => '',
                    'shippitTrackingCode' => '',
                ]
            );
            if ($orderCreated) {
                $orderCreated->update(['pabitra' => 1]);
            }
        } catch (\Exception $e) {
            Log::error('Error in processing shopify order ' . $e->getMessage());
        }
    }

    private function processShopifyProduct($sourceProduct, $shopifyOrderStringId)
    {
        try {
            $orderProductCreated = OrderProduct::updateOrcreate(
                ['newSystemProductId' => $sourceProduct->shopify_product_string_id],
                [
                    'newSystemOrderID' => $shopifyOrderStringId,
                    'newSystemProductId' => $sourceProduct->shopify_product_string_id,
                    'stockCode' => $sourceProduct->product_sku,
                    'actualCIStockCode' => $sourceProduct->product_sku,
                    'colour' => $sourceProduct->color,
                    'size' => $sourceProduct->size,
                    'quantity' => $sourceProduct->quantity,
                    'unitPrice' => $sourceProduct->variant_price,
                    'discountAmount' => $sourceProduct->discount_amount,
                ]
            );

            if ($orderProductCreated) {
                $orderProductCreated->update(['pabitra' => 1]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating order product: ' . $e->getMessage());
        }
    }

    private function processShopifyCustomer($sourceCustomer, $shippingAddress, $billingAddress, $shopifyOrderStringId, $deliveryMethod, $debug, $Customer)
    {
        try {
            $addressToUse = empty($shippingAddress) ? $billingAddress : $shippingAddress;
            $orderDeliveryCreated = OrderDelivery::updateOrCreate(
                ['newSystemOrderID' => $shopifyOrderStringId],
                [
                    'newSystemMemberID' => $sourceCustomer->id,
                    'newSystemOrderID' => $shopifyOrderStringId,
                    'deliveryFirstName' => $addressToUse->firstName ?? null,
                    'deliveryLastName' => $addressToUse->lastName ?? null,
                    'deliveryPhone' => $addressToUse->phone ?? null,
                    'deliveryStreet' => $addressToUse->address1 ?? null,
                    'deliverySuburb' => $addressToUse->address2 ?? null,
                    'deliveryPostCode' => $addressToUse->zip ?? null,
                    'deliveryCountry' => $addressToUse->country ?? null,
                    'deliveryCity' => $addressToUse->city ?? null,
                    'deliveryState' => $addressToUse->province ?? null,
                ]
            );
            if ($orderDeliveryCreated) {
                $orderDeliveryCreated->update(['pabitra' => 1]);
            }
            $email = $sourceCustomer->email;
            $customerData = [
                'emailAddress' => $email ?? null,
                'firstName' => $sourceCustomer->firstName ?? null,
                'lastName' => $sourceCustomer->lastName ?? null,
                'street' => $sourceCustomer->defaultAddress->address1 ?? null,
                'city' => $sourceCustomer->defaultAddress->city ?? null,
                'postCode' => $sourceCustomer->defaultAddress->zip ?? null,
                'country' => $sourceCustomer->defaultAddress->country ?? null,
                'state' => $sourceCustomer->defaultAddress->province ?? null,
                'middleEntryDate' => $Customer->created_at ?? null,
                'lastCheckDate' => $Customer->created_at ?? null,
                'pabitra' => 1,
            ];
            $ErplyDeliveryCustomer = Customer::updateOrCreate(
                ['newSystemMemberID' => $sourceCustomer->id],
                $customerData
            );
        } catch (\Exception $e) {
            Log::error('Error processing Shopify customer: ' . $e->getMessage());
            if ($debug) {
                throw $e;
            }
        }
    }

    public function getRefunds(Request $request)
    {
        
        die('please refer to getRefundsV2');
        date_default_timezone_set('Australia/Melbourne');
        $debug = 0;

        if (isset($request->debug)) {
            $debug = $request->debug;
        }

        $query = $this->getRefundQuery($this->clientCode, $this->live);
        if ($debug == 1) {
            dd($query);
        }
        $datas = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);

        if ($debug == 2) {
            dd($datas);
        }

        if (!empty($datas->data->orders->edges)) {
            DB::beginTransaction();
            try {
                $lmd = '';

                foreach ($datas->data->orders->edges as $edge) {

                    $order = $edge->node;
                    $cursor = $edge->cursor;

                    $lmd = $order->updatedAt;
                    if ($debug == 3) {
                        dd($cursor, $order, $edge);
                    }
                    $shopifyOrderIdParts = explode('/', $order->id);
                    $shopifyOrderId = end($shopifyOrderIdParts);
                    $updateAt = $order->updatedAt;
                    $shopifyRefundIdParts = explode('/', $order->refunds[0]->id);
                    $shopifyRefundId = end($shopifyRefundIdParts);
                    $totalRefunded = $order->refunds[0]->totalRefundedSet->shopMoney->amount;
                    if ($debug == 4) {
                        dd([
                            'shopify_refund_id' => $shopifyRefundId,
                        ], [
                            'shopify_refund_id' => $shopifyRefundId,
                            'shopify_order_id' => $shopifyOrderId,
                            'shopify_refund_string_id' => $order->refunds[0]->id,
                            'order_number' => $order->name,
                            'order_updated_date' => $order->updatedAt,
                            'refund_created_exact' => $order->createdAt,
                            'total_refunded' => $totalRefunded,
                            'refund_type' => $order->displayFinancialStatus,
                            'refund_reason' => $order->refunds[0]->note,
                            'refund_shipping_amount' => @$order->refunds[0]->order->totalRefundedShippingSet->shopMoney->amount ?? 0,
                            'Shopify_cursor' => $cursor,
                        ]);
                    }
                    $sourceRefund = SourceRefund::updateOrCreate([
                        'shopify_refund_id' => $shopifyRefundId,
                    ], [
                        'shopify_refund_id' => $shopifyRefundId,
                        'shopify_order_id' => $shopifyOrderId,
                        'shopify_refund_string_id' => $order->refunds[0]->id,
                        'order_number' => $order->name,
                        'order_updated_date' => $order->updatedAt,
                        'refund_created_exact' => $order->createdAt,
                        'total_refunded' => $totalRefunded,
                        'refund_type' => $order->displayFinancialStatus,
                        'refund_reason' => $order->refunds[0]->note,
                        'refund_shipping_amount' => @$order->refunds[0]->order->totalRefundedShippingSet->shopMoney->amount ?? 0,
                        'Shopify_cursor' => $cursor,
                    ]);

                    $orderLineProducts = '';
                    $orderLineProductsPrice = '';
                    $orderLineProductsQty = '';
                    $orderLineProductsID = '';
                    $totalProductPrice = 0;
                    $shopifyProductStringIds = '';
                    $shopifyLocationIds = '';
                    foreach ($order->refunds[0]->refundLineItems->edges as $key1 => $refundLineItemEdge) {
                        $refundLineItem = $refundLineItemEdge->node;
                        $lineItem = $refundLineItem->lineItem;
                        $shopifyProductIdParts = explode('/', $lineItem->id);
                        $shopifyProductId = end($shopifyProductIdParts);
                        $delimiter = $key1 > 0 ? ',' : '';
                        $shopifyProductStringIds .= $delimiter . $lineItem->id;
                        $orderLineProducts .= $key1 > 0 ? ',' . $lineItem->sku : $lineItem->sku;
                        $orderLineProductsPrice .= $key1 > 0 ? ',' . $refundLineItem->priceSet->shopMoney->amount : $refundLineItem->priceSet->shopMoney->amount;
                        $orderLineProductsQty .= $key1 > 0 ? ',' . $refundLineItem->quantity : $refundLineItem->quantity;
                        $orderLineProductsID .= $key1 > 0 ? ',' . $shopifyProductId : $shopifyProductId;
                        $shopifyLocationIds .= $key1 > 0 ? ',' . $refundLineItem->location->id : $refundLineItem->location->id;
                        $totalProductPrice += $refundLineItem->priceSet->shopMoney->amount;
                    }

                    $refundLineItemDetails = SourceRefundLineItem::updateOrCreate(
                        ['shopify_refund_id' => $shopifyRefundId],
                        [
                            'shopify_product_string_id' => $shopifyProductStringIds,
                            'shopify_location_id' => $shopifyLocationIds,
                            'shopify_product_id' => $orderLineProductsID,
                            'product_quantity' => $orderLineProductsQty,
                            'product_code' => $orderLineProducts,
                            'product_price' => $orderLineProductsPrice,
                            'Shopify_cursor' => $cursor,
                        ]
                    );

                    //dump($refundLineItemdetails,$sourceRefund,$orderLineProducts,$orderLineProductsPrice,$orderLineProductsQty,$orderLineProductsID,$shopifyRefundId,$updateAt,$sourceRefund->order_updated_date);
                    $this->processShopifyRefund($order, $sourceRefund, $orderLineProducts, $shopifyLocationIds, $orderLineProductsPrice, $orderLineProductsQty, $orderLineProductsID, $shopifyRefundId, $updateAt, $debug, $totalProductPrice, @$order->refunds[0], $cursor);
                    // }
                }

                /** update or create cursor **/
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
                    ShopifyCursor::updateOrcreate(
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'refundDate',
                            'isLive' => $this->live,
                        ],
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'refundDate',
                            'cursor' => $lmd,
                            'isLive' => $this->live,
                        ]
                    );

                    DB::commit();
                }
            } catch (\Exception $e) {
                DB::rollBack();
                dd($e);
            }
            dd(@$datas);
        } else {
            return response('No Refunds Found to Sync');
        }
    }

    public function getRefundsV2(Request $request)
    {
        date_default_timezone_set('Australia/Melbourne');
        $debug = 0;

        if (isset($request->debug)) {
            $debug = $request->debug;
        }

        $query = $this->getRefundQuery($this->clientCode, $this->live);
        if ($debug == 1) {
            dd($query);
        }
        $datas = $this->sendShopifyQueryRequestV2('POST', $query, $this->live);

        if ($debug == 2) {
            dd($datas);
        }

        if (!empty($datas->data->orders->edges)) {
            DB::beginTransaction();
            try {
                $lmd = '';

                foreach ($datas->data->orders->edges as $key =>  $edge) {
                    
                // if($key ==0) continue;
                    $order = $edge->node;
                    $cursor = $edge->cursor;

                    $lmd = $order->updatedAt;
                    if ($debug == 3) {
                        dd($cursor, $order, $edge);
                    }
                    $shopifyOrderIdParts = explode('/', $order->id);
                    $shopifyOrderId = end($shopifyOrderIdParts);


                    // ---------------- TRACK SHIPPING ------------------
                    $shippingStored = Refund::where('newsystemOrderId', $shopifyOrderId)
                        ->sum('refund_shipping_amount');

                    $pendingShipping = 0; // Shipping amount to be applied
                    $productRefundFound = false; // Tracks if we have seen a product refund


                    foreach ($order->refunds as $row) {
                        $shopifyRefundIdParts = explode('/', $row->id);
                        $shopifyRefundId = end($shopifyRefundIdParts);
                        $totalRefunded = $row->totalRefundedSet->shopMoney->amount;
                        SourceRefund::updateOrCreate([
                                'shopify_refund_id' => $shopifyRefundId,
                            ], [
                                'shopify_refund_id' => $shopifyRefundId,
                                'shopify_order_id' => $shopifyOrderId,
                                'shopify_refund_string_id' => $row->id,
                                'order_number' => $order->name,
                                'order_updated_date' => $row->updatedAt,
                                'refund_created_exact' => $row->createdAt,
                                'total_refunded' => $totalRefunded,
                                'refund_type' => $order->displayFinancialStatus,
                                'refund_reason' => $row->note,
                                'refund_shipping_amount' => @$row->order->totalRefundedShippingSet->shopMoney->amount ?? 0,
                                'Shopify_cursor' => $cursor
                            ]
                        );

                        $orderLineProducts = '';
                        $orderLineProductsPrice = '';
                        $orderLineProductsQty = '';
                        $orderLineProductsID = '';
                        $totalProductPrice = 0;
                        $shopifyProductStringIds = '';
                        $shopifyLocationIds = '';

                        foreach ($row->refundLineItems->edges as $key1 => $refundLineItemEdge) {
                            $refundLineItem = $refundLineItemEdge->node;
                            $lineItem = $refundLineItem->lineItem; // (sku, id)
                            $shopifyProductIdParts = explode('/', $lineItem->id);
                            $shopifyProductId = end($shopifyProductIdParts); // Only the number
                            $shopifyProductStringIds .=  $key1 > 0 ? ',' . $lineItem->id : $lineItem->id;
                            $orderLineProducts .= $key1 > 0 ? ',' . $lineItem->sku : $lineItem->sku;
                            $orderLineProductsPrice .= $key1 > 0 ? ',' . $refundLineItem->priceSet->shopMoney->amount : $refundLineItem->priceSet->shopMoney->amount;
                            $orderLineProductsQty .= $key1 > 0 ? ',' . $refundLineItem->quantity : $refundLineItem->quantity;
                            $orderLineProductsID .= $key1 > 0 ? ',' . $shopifyProductId : $shopifyProductId;
                            $shopifyLocationIds .= $key1 > 0 ? ',' . $refundLineItem->location->id : $refundLineItem->location->id;
                            $totalProductPrice += $refundLineItem->priceSet->shopMoney->amount;
                        }

                        SourceRefundLineItem::updateOrCreate(
                            ['shopify_refund_id' => $shopifyRefundId],
                            [
                                'shopify_product_string_id' => $shopifyProductStringIds,
                                'shopify_location_id' => $shopifyLocationIds,
                                'shopify_product_id' => $orderLineProductsID,
                                'product_quantity' => $orderLineProductsQty,
                                'product_code' => $orderLineProducts,
                                'product_price' => $orderLineProductsPrice,
                                'Shopify_cursor' => $cursor
                            ]
                        );


                        // ----------------- SHIPPING / REFUND LOGIC ------------------
                        $isShippingOnly = empty($row->refundLineItems->edges ?? []);
                        $shippingFromShopify = (float) (@$row->order->totalRefundedShippingSet->shopMoney->amount ?? 0);
                       
                        if ($isShippingOnly) {
                            // Accumulate shipping
                            $pendingShipping += $shippingFromShopify;
                            continue; // Skip saving this row
                        }
                         $pendingShipping = $shippingFromShopify;

                        // Product refund found → apply pending shipping
                        $productRefundFound = true;
                        // dd($pendingShipping, $shippingStored);
                        // Calculate remaining shipping to store
                        $refundShippingAmount = $pendingShipping;
                        if($pendingShipping !== $shippingStored ){

                        $refundShippingAmount = max($pendingShipping - $shippingStored, 0);
                        }


                        // dd($refundShippingAmount);
                        $shippingStored += $refundShippingAmount;
                        // $pendingShipping = 0;
                        // ----------------- CALL PROCESS FUNCTION ------------------
                        $this->processShopifyRefundV2(
                            $order,
                            $orderLineProducts,
                            $shopifyLocationIds,
                            $orderLineProductsPrice,
                            $orderLineProductsQty,
                            $orderLineProductsID,
                            $shopifyRefundId,
                            $row,
                            $cursor,
                            $refundShippingAmount
                        );
                    }
                    // ---------------- HANDLE CASE: all refunds shipping-only -----------------
                    if (!$productRefundFound && $pendingShipping > 0) {
                        // Create a single row for shipping-only refunds
                        $shippingOnlyRefund = $order->refunds[0]; // pick first refund
                        $shopifyRefundIdParts = explode('/', $shippingOnlyRefund->id);
                        $shopifyRefundId = end($shopifyRefundIdParts);

                        $this->processShopifyRefundV2(
                            $order,
                            '', // no products
                            '', // no locations
                            '', // no product prices
                            '', // no qty
                            '', // no product IDs
                            $shopifyRefundId,
                            $shippingOnlyRefund,
                            $cursor,
                            $pendingShipping // apply all shipping
                        );
                    }
                }

                /** update or create cursor **/
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
                    ShopifyCursor::updateOrcreate(
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'refundDate',
                            'isLive' => $this->live,
                        ],
                        [
                            'clientCode' => $this->clientCode,
                            'cursorName' => 'refundDate',
                            'cursor' => $lmd,
                            'isLive' => $this->live,
                        ]
                    );

                    DB::commit();
                }
            } catch (\Exception $e) {
                DB::rollBack();
                dd($e);
            }
            dd(@$datas);
        } else {
            return response('No Refunds Found to Sync');
        }
    }

    private function processShopifyRefund($order, $sourceRefund, $refundProducts, $shopifyLocationIds, $refundPrices, $refundQty, $orderLineProductsID, $shopifyRefundId, $updateAt, $debug, $totalProductPrice, $refund, $cursor)
    {

        $shopifyOrderIdParts = explode('/', $order->id);
        $shopifyOrderId = end($shopifyOrderIdParts);
        $totalProductPrice = $totalProductPrice;
        $refund_amount = $refund->totalRefundedSet->shopMoney->amount;

        $refundedShippingAmt = @$refund->order->totalRefundedShippingSet->shopMoney->amount ?? 0;
        if ($debug == 5) {
            dd($refundPayload = [
                'shopifyRefundString' => $refund->id,
                'orderUpdateDate' => date('Y-m-d h:i:s', strtotime($order->updatedAt)),
                'orderUpdateZtime' => $order->updatedAt,
                'refund_amount' => $refund_amount,
                'refund_type' => '',
                'refund_product_id' => $orderLineProductsID,
                'refund_location_id' => $shopifyLocationIds,
                'refund_product_code' => $refundProducts,
                'refund_product_price' => $refundPrices,
                'refund_shipping_amount' => $refundedShippingAmt,
                'refund_product_qty' => $refundQty,
                'refund_reason' => $refund->note,
                'newsystemRefundId' => $shopifyRefundId,
                'newSystemOrderNumber' => $order->name,
                'newsystemOrderId' => $shopifyOrderId,
                'shopify_cursor' => $cursor,
            ]);
        }
        $refundPayload = [
            'shopifyRefundString' => $refund->id,
            'orderUpdateDate' => date('Y-m-d h:i:s', strtotime($order->updatedAt)),
            'orderUpdateZtime' => $order->updatedAt,
            'refund_amount' => $refund_amount,
            'refund_type' => '',
            'refund_product_id' => $orderLineProductsID,
            'refund_location_id' => $shopifyLocationIds,
            'refund_product_code' => $refundProducts,
            'refund_product_price' => $refundPrices,
            'refund_shipping_amount' => $refundedShippingAmt,
            'refund_product_qty' => $refundQty,
            'refund_reason' => $refund->note,
            'newsystemRefundId' => $shopifyRefundId,
            'newSystemOrderNumber' => $order->name,
            'newsystemOrderId' => $shopifyOrderId,
            'shopify_cursor' => $cursor,
        ];

        try {
            $refundCreated = Refund::updateOrcreate(
                [
                    'shopifyRefundString' => $refund->id,
                ],
                $refundPayload
            );
            if ($refundCreated) {
                $refundCreated->update(['pabitra' => 1]);
            }
        } catch (\Exception $ex) {
            dd($ex);
        }
    }

    private function processShopifyRefundV2(
        $order,
        $refundProducts,
        $shopifyLocationIds,
        $refundPrices,
        $refundQty,
        $orderLineProductsID,
        $shopifyRefundId,
        $refund,
        $cursor,
        $refundShippingAmount // pass shipping only once
    ) {
        $shopifyOrderIdParts = explode('/', $order->id);
        $shopifyOrderId = end($shopifyOrderIdParts);

        $refundAmount = (float) ($refund->totalRefundedSet->shopMoney->amount ?? 0);
        try {
            Refund::updateOrCreate(
                ['shopifyRefundString' => $refund->id],
                [
                    'shopifyRefundString'   => $refund->id,
                    'orderUpdateDate'       => date('Y-m-d H:i:s', strtotime($order->updatedAt)),
                    'orderUpdateZtime'      => $order->updatedAt,
                    'refund_amount'         => $refundAmount,
                    'refund_product_id'     => $orderLineProductsID,
                    'refund_location_id'    => $shopifyLocationIds,
                    'refund_product_code'   => $refundProducts,
                    'refund_product_price'  => $refundPrices,
                    'refund_shipping_amount' => $refundShippingAmount, // ✅ shipping added only once
                    'refund_product_qty'    => $refundQty,
                    'refund_reason'         => $refund->note,
                    'newsystemRefundId'     => $shopifyRefundId,
                    'newSystemOrderNumber'  => $order->name,
                    'newsystemOrderId'      => $shopifyOrderId,
                    'shopify_cursor'        => $cursor,
                    'pabitra'               => 1
                ]
            );
        } catch (\Exception $ex) {
            dd($ex);
        }
    }
}
