<?php

namespace Modules\Shopify\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
class VerifyCSRFToken extends Middleware
{
    /**
     * Handle an incoming request.
     */
    protected $except = [

       'api/shopify/webhooks/orders'
    ];
}