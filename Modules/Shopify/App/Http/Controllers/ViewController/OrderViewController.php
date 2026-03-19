<?php

namespace Modules\Shopify\App\Http\Controllers\ViewController;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Shopify\App\Services\ErplyService\ErplyProductService;

class OrderViewController extends Controller
{
    private $erplyService;
    public function __construct(ErplyProductService $erplyService)
    {
        $this->erplyService = $erplyService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $orderby = $request->orderby ?? 'desc';
        $order = $request->order ?? 'order_created';
        $orders = $this->erplyService->getOrdersDetails($orderby, $order);
        return view('shopify::orders.index', compact('orders'));
    }

    public function deleteLaravelLogs()
    {
        $logFilePath = storage_path('logs/laravel.log');

        if (file_exists($logFilePath)) {
            file_put_contents($logFilePath, '');
            return response()->json(['message' => 'Log file emptied successfully'], 200);
        } else {
            return response()->json(['message' => 'Log file not found'], 404);
        }
    }
}
