<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;
use Osiset\ShopifyApp\Objects\Values\Hmac;
use Osiset\ShopifyApp\Objects\Values\NullableShopDomain;
use Osiset\ShopifyApp\Util;

/**
 * Verifies GDPR compliance webhooks with HMAC signatures.
 * Returns 400 (not 401) on failure, as required by Shopify's automated checks.
 */
class AuthWebhookGdpr
{
    /**
     * Handle an incoming GDPR webhook request.
     */
    public function handle(Request $request, Closure $next)
    {
        $hmac = Hmac::fromNative($request->header('x-shopify-hmac-sha256', ''));
        $shop = NullableShopDomain::fromNative($request->header('x-shopify-shop-domain'));
        $data = $request->getContent();
        $hmacLocal = Util::createHmac(
            [
                'data' => $data,
                'raw' => true,
                'encode' => true,
            ],
            Util::getShopifyConfig('api_secret', $shop)
        );

        if (! $hmac->isSame($hmacLocal) || $shop->isNull()) {
            // Shopify automated checks expect 400, not 401
            return Response::make('Invalid webhook signature.', HttpResponse::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
