<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FromShopify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Webhook verification.
        if ( isset( $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ) ) {
            
            $data = file_get_contents('php://input');
            
             if( FromShopify::verify_webhook($data,$_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ) ){
                return $response;
             }else{
                return response('This Request is not From Shopify.', 401);
             }
        }

        // normal route.
        if( isset( $_REQUEST['shop'] ) && !isset( $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ) ){
            $shop = $_REQUEST['shop'];
            
            $response->headers->set('Content-Security-Policy', 'frame-ancestors https://admin.shopify.com '.$shop);
            return $response;
        }
        else{
            return response('This Request is not From Shopify.', 401);
        }

        return response('This Request is not From Shopify.', 401);
    }

    public static function verify_webhook($data, $hmac_header)
    {
      $calculated_hmac = base64_encode(hash_hmac('sha256', $data, env('SHOPIFY_API_SECRET'), true));
      return hash_equals($hmac_header, $calculated_hmac);
    }
}
