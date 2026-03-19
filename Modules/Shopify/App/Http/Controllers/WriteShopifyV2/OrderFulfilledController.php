<?php

namespace Modules\Shopify\App\Http\Controllers\WriteShopifyV2;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Modules\Shopify\App\Models\Source\SourceOrder;
use Modules\Shopify\App\Traits\ShopifyTrait;

class OrderFulfilledController extends Controller
{
    use ShopifyTrait;

    public  $live = 1;
    /**
     * Updates the fulfillment status of orders from Shopify.
     *
     * Request parameters:
     * - `orderId`: The Shopify order ID to update. If not provided, all unfulfilled orders will be retrieved.
     * - `debug`: An integer value indicating the level of debug output. Possible values are:
     *   0: No debug output
     *   1: Dump orders retrieved from database
     *   2: Dump Shopify order query
     *   3: Dump unfulfilled order data from Shopify
     *   4: Dump fulfillment creation query
     * - `limit`: The maximum number of orders to retrieve. Defaults to 2.
     *
     * Returns a JSON response with the fulfillment status of the orders.
     * If successful, the response will contain the fulfillment status of each order.
     * If an error occurs, the response will contain the error message.
     */
    public function index(Request $request)
    {
        // Retrieve request parameters or set default values
        $orderId = $request->orderId ?? null;
        $debug = $request->debug ?? 0;
        $limit = $request->limit ?? 2;

        try {
            // Query the SourceOrder model
            $orders = SourceOrder::query()
                ->when($orderId != null, function ($query) use ($orderId) {
                    // Filter orders by Shopify order ID if provided
                    return $query->where('shopify_order_id', $orderId);
                })
                ->where('fullfillment_status', 'UNFULFILLED')
                ->where('is_invoice_fulfilled', 1) // Ensure orders are marked as invoice fulfilled
                ->limit($limit) // Limit the number of orders retrieved
                ->get();

            // Check if no orders were retrieved
            if (count($orders) <= 0) {
                return response()->json([
                    'message' => 'No Pending Orders'
                ], 404);
            }

            // Debug: Dump orders if debug level is 1
            if ($debug == 1) {
                dd($orders);
            }

            $response = [];
            foreach ($orders as $order) {
                // Fetch unfulfilled order details from Shopify
                $shopifyOrder = $this->getUnfulfilledOrdeQuery($order->shopify_order_string_id);

                // Debug: Dump Shopify order query if debug level is 2
                if ($debug == 2) {
                    dd($shopifyOrder);
                }

                // Send Shopify query request to get unfulfilled order data
                $unfulfilledOrder = $this->sendShopifyQueryRequestV2(
                    'POST',
                    $shopifyOrder,
                    $this->live
                );

                // Debug: Dump unfulfilled order data if debug level is 3
                if ($debug == 3) {
                    dd($unfulfilledOrder);
                }

                // Create fulfillment mutation query for Shopify
                $fulfillmentCreateV2 = $this->fulfillmentCreateV2Mutation(
                    @$unfulfilledOrder->data->order->fulfillmentOrders->edges[0]->node->id,
                    @$unfulfilledOrder->data->order->fulfillmentOrders->edges[0]->node->lineItems->edges,
                );

                // Debug: Dump fulfillment creation query if debug level is 4
                if ($debug == 4) {
                    dd($fulfillmentCreateV2);
                }

                // Send query to Shopify to create fulfillment
                $fulfillmentCreateV2Response = $this->sendShopifyQueryRequestV2(
                    'POST',
                    $fulfillmentCreateV2,
                    $this->live
                );

                // Update order status based on Shopify response
                if (@$fulfillmentCreateV2Response->data->fulfillmentCreateV2->fulfillment->id) {
                    $order->update(['is_invoice_fulfilled' => 2]); // Mark as fulfilled
                } else {
                    $order->update(['is_invoice_fulfilled' => 3]); // Mark as failed to fulfill
                }

                // Store response data for each order FULFILLED
                $response[$order->shopify_order_string_id] = $fulfillmentCreateV2Response;
            }

            // Return a JSON response indicating orders were fulfilled
            return response()->json([
                'message' => 'Order Fulfilled',
                'data' => $response
            ], 200);

        } catch (Exception $th) {
            // Return a JSON response with the exception message in case of an error
            return response()->json([
                'message' => $th->getMessage(),
            ]);
        }
    }
}
