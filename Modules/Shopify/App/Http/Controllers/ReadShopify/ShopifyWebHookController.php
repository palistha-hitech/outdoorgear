<?php

namespace Modules\Shopify\App\Http\Controllers\ReadShopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Shopify\App\Models\Source\SourceOrder;
use Modules\Shopify\App\Models\Source\SourceOrderDelivery;
use Modules\Shopify\App\Models\Source\SourceOrderProduct;
use Modules\Shopify\App\Services\ShopifyOrderService;

class ShopifyWebHookController extends Controller
{
    public function handleOrderwebHooks(Request $request)
    {
        $data = $request->all();
        info($data);
        Log::info('Received Shopify order webhook: ' . json_encode($data));
        $orderDetails = $data;
        // try {
        //     $sourceOrder = SourceOrder::updateOrCreate(
        //         ['shopify_order_id' => $orderDetails['id']],
        //         [
        //             'shopify_order_string_id' => $orderDetails['admin_graphql_api_id'],
        //             'shpify_order_number' => $orderDetails['order_number'],
        //             'total_order' => $orderDetails['current_total_price'],
        //             'subtotal_order' => $orderDetails['current_subtotal_price'],
        //             'tax_amount' => $orderDetails['total_tax'],
        //             'total_shipping' => $orderDetails['total_shipping_price_set']['shop_money']['amount'] ?? 0,
        //             'currency' => $orderDetails['currency'],
        //             'subtotal_price' => $orderDetails['current_subtotal_price'],
        //             'total_price' => $orderDetails['current_total_price'],
        //             'total_tax' => $orderDetails['current_total_tax'],
        //             'order_created' => $orderDetails['created_at'],
        //             'order_completed' => $orderDetails['updated_at'],
        //             'fullfillment_status' => $orderDetails['fulfillment_status'],
        //             'note' => $orderDetails['note'] ?? '',
        //             'shipping_method' => $orderDetails['shipping_lines'][0]['title'] ?? 'Standard',
        //             'total_items' => count($orderDetails['line_items']),
        //             'coupon_code' => implode(',', array_column($orderDetails['discount_codes'], 'code')),
        //             'coupon_amount' => array_sum(array_column($orderDetails['discount_codes'], 'amount')),
        //         ]
        //     );
        //     foreach ($orderDetails['line_items'] as $lineItem) {
        //         SourceOrderProduct::updateOrCreate(
        //             ['shopify_product_id' => $lineItem['id']],
        //             [
        //                 'shopify_product_string_id' => $lineItem['admin_graphql_api_id'],
        //                 'shopify_order_id' => $orderDetails['id'],
        //                 'quantity' => $lineItem['quantity'],
        //                 'price' => $lineItem['price'],
        //                 'product_sku' => $lineItem['sku'],
        //                 'title' => $lineItem['name'],
        //                 'variant_sku',
        //                 'variant_title' => $lineItem['variant_title'] ?? NULL,
        //                 'variant_price',
        //                 'discount_amount' => $lineItem['total_discount_set']['shop_money']['amount'],
        //                 'total_discount' => $lineItem['total_discount'],

        //             ]
        //         );
        //     }
        //     if (isset($orderDetails['customer'])) {
        //         $shippingAddress = $orderDetails['customer'];
        //         SourceOrderDelivery::updateOrCreate(
        //             ['shopify_order_id' => $orderDetails['id']],
        //             [
        //                 'shopify_customer_string_id' => $shippingAddress['admin_graphql_api_id'],
        //                 'shopify_customer_id' => $shippingAddress['id'],
        //                 'first_name' => $shippingAddress['default_address']['first_name'],
        //                 'last_name' => $shippingAddress['default_address']['last_name'],
        //                 'address1' => $shippingAddress['default_address']['address1'],
        //                 'city' => $shippingAddress['default_address']['city'],
        //                 'zip' => $shippingAddress['default_address']['zip'],
        //                 'province' => $shippingAddress['default_address']['province'],
        //                 'country' => $shippingAddress['default_address']['country'],
        //                 'email' => $shippingAddress['email'],
        //                 'address2' => $shippingAddress['default_address']['address2'],
        //                 'defalut_phone' => $shippingAddress['default_address']['phone'],
        //                 'billing_address_first_name' => $orderDetails['billing_address']['first_name'],
        //                 'billing_address_last_name' => $orderDetails['billing_address']['last_name'] ?? NULL,
        //                 # 'billing_address_email'=>$orderDetails['billing_address'][''],
        //                 'billing_address_city' => $orderDetails['billing_address']['city'],
        //                 'billing_address_province' => $orderDetails['billing_address']['province'],
        //                 'billing_address_country' => $orderDetails['billing_address']['country'],
        //                 'billing_address_zip' => $orderDetails['billing_address']['zip'],
        //                 'billing_address_phone' => $orderDetails['billing_address']['phone'],
        //                 'shipping_address_first_name' => $orderDetails['shipping_address']['first_name'],
        //                 'shipping_address_last_name' => $orderDetails['shipping_address']['last_name'] ?? NULL,
        //                 #  'shipping_address_email'=>$orderDetails['shipping_address'],
        //                 'shipping_address_phone' => $orderDetails['shipping_address']['phone'],
        //                 'shipping_address_city' => $orderDetails['shipping_address']['city'],
        //                 'shipping_address_province' => $orderDetails['shipping_address']['province'],
        //                 'shipping_address_country' => $orderDetails['shipping_address']['country'],
        //                 'shipping_address_zip' => $orderDetails['shipping_address']['zip'],
        //                 'shipping_address_phone' => $orderDetails['shipping_address']['phone'],
        //             ]
        //         );
        //     }
        // } catch (\Exception $e) {
        //     Log::error("Error inserting order: " . $e->getMessage());
        // }


        # Responding to the webhook
        return response()->json(['status' => 'success'], 200);
    }
}