<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Http\Controllers\ShopController;

class WebhookController extends Controller
{


    public static function create_uninstall_webhook($param) {
        $shop = $param['shop'];
        $token = Store::where('shop_url', $shop)->value('shopify_token');

        $api_endpoint = '/admin/webhooks.json';

        $query["webhook"] = array(
          'topic' => 'app/uninstalled',
          'format' => 'json',
          'address' => secure_url('uninstall'),
      );

        $method = 'POST';

        $api_response = ShopController::shopify_rest_call($token, $shop, $api_endpoint, $query, $method);

        return json_decode($api_response['response']);
    }
}
