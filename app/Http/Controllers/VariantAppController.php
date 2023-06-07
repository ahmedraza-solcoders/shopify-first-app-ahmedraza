<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use  App\Http\Controllers\AppController;
use App\Http\Controllers\ShopController;
use App\Models\Store, App\Models\Plan;

class VariantAppController extends Controller
{
    public function getVariants(Request $request){

        $shop = $request->shop;
        if (!str_contains($shop, '.myshopify.com')) {
           $shop = AppController::getShopifyDomain($shop);
        }
        $store = Store::where('shop_url', $request->shop)->first();

        if( !empty( $store->shopify_token ) ){
            $settings= $store->variant_settings;
            if (is_string($settings)) {
                $settings = json_decode($store->variant_settings);
            }
            if($request->productid){
                if($settings->product_feature_enable == 1 &&  !empty($settings->include_products)  && !in_array($request->productid, $settings->include_products)){
                    $html = 'No Record Found';
                }
                else{
                    $name = in_array("name", $settings->active_column) ? true : false;
                    $imageColumn = in_array("image", $settings->active_column) ? true : false;
                    $quantity = in_array("quantity", $settings->active_column) ? true : false;
                    $price = in_array("price", $settings->active_column) ? true : false;
                    if($settings->product_feature_enable == 1){
                        $btnStyle = 'style="background-color: '.$settings->btn_bg.' !important; border-color: '.$settings->btn_bg.' !important; color: '.$settings->btn_color.' !important"';
                        $btnText = $settings->btn_text;
                    }
                    else{
                        $btnStyle = '';
                        $btnText = 'Add to Cart';

                    }
                    $productid = $request->productid;
                    $currency = $request->currency;
                    $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2022-07').'/products/'.$productid.'.json';

                    $product = ShopController::shopify_rest_call($store->shopify_token, $request->shop, $api_endpoint , array() ,'GET');
                    $product = json_decode($product['response']);
                    $product = $product->product;
                    $html = '';
                    $options = $product->options;
                    if(count($product->variants) > 1){
                        foreach($product->variants as $variant){
                            $qty = "in stock";
                            if ("shopify" == $variant->inventory_management) {
                                $qty = $variant->inventory_quantity;
                                if ("deny" == $variant->inventory_policy) {
                                    $qty = $qty . " in stock";
                                } else if ("continue" == $variant->inventory_policy) {
                                    $qty = $qty . " in stock<br><p>Available on backorder</p>";
                                }
                            }
                            $image = "";
                            if(isset($variant->image_id)){
                                foreach($product->images as $productimage){
                                    if($variant->image_id == $productimage->id){
                                        $image = $productimage->src;
                                        break;
                                    }
                                }
                            }
                            else{
                                $image = env('APP_URL').'images/white-image.png';
                            }
                            $html .= '<tr>';
                            if($name){
                                $html .= '  <td> ';
                                foreach($options  as $key => $option){
                                    $var = 'option'.($key+1);
                                    $optionName = $variant->{$var};
                                    $html .= '<p><strong>'.$option->name.': </strong>'.$optionName.' </p> ';
                                }
                                $html .= '<p><strong>SKU: </strong>'.$variant->sku.' </p> </td>';
                            }
                            if($imageColumn){
                                $html .= '  <td><img width="50" height="50" src="'.$image.'" alt="'.$variant->title.'"></td>';
                            }
                            if($price){
                                $html .= '  <td>'.$currency.' '.($variant->price).'</td>';
                            }
                            if($quantity){


                                if ("shopify" == $variant->inventory_management) {
                                    if($variant->inventory_quantity <= 0 && "deny" == $variant->inventory_policy){
                                        $disabled = 'disabled="true"';
                                    }
                                    else{
                                        $disabled = '';
                                    }
                                }
                                else{
                                    $disabled = '';
                                }
                                $html .= '  <td>'.$qty.'</td>';
                            }
                            $html .= '  <td> <input id="qty-' . $variant->id . '" class="form-control productquantity mb-2" type="number" value="1" min="1" '.$disabled.' style="width:40px !important;"  ><button type="button" data-productid="' . $variant->product_id . '" data-variant="' . $variant->id . '" class="btn btn-info svta-add-to-cart-btn ' . $variant->id . '"  '.$disabled.'  '.$btnStyle.' >'.$btnText.'</button></td>';
                            $html .= '</tr>';
                        }
                    }
                    else{
                        $html = 'No Record Found';
                    }
                }

            }
            else{
                $html = 'No Record Found';
            }

            return json_encode($html);
        }
    }

    public function saveVariantTableSettings(Request $request) {
        $data = $request->all();
        $shop = $data['shop'];
        $store = Store::where('shop_url',$data['shop'])->first();
        $setting['name'] = $data['name'];
        $setting['image'] = $data['image'];
        $setting['quantity'] = $data['quantity'];
        $setting['price'] = $data['price'];
        if(isset($data['active_column'])){
            $setting['active_column'] = $data['active_column'];
        }
        if (isset($data['product_feature_enable']) &&  $data['product_feature_enable'] == 1) {
            $setting['product_feature_enable'] = $data['product_feature_enable'];

            if (isset($data['check_product'])) {
                $setting['check_product'] = $data['check_product'];
                $setting['include_products'] = isset($data['include_products']) ? $data['include_products'] : "";
            }
        }
        else{
            $setting['product_feature_enable'] = 2;
            $setting['check_product'] = 2;

        }


        if (isset($data['product_feature_enable']) &&  $data['product_feature_enable'] == 1) {
            $setting['product_feature_enable'] = $data['product_feature_enable'];
            if(isset($data['include_products']) && !empty($data['include_products'])){
                $setting['include_products'] = isset($data['include_products']) ? $data['include_products'] : "";
                $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', "2023-01") . '/metafields.json';
                $solcoders_metafields = [
                    "metafield" => ["namespace" => "solcoders", "key" => "specific_products", "value" => json_encode($setting['include_products']), "type" => "multi_line_text_field"]
                ];
                $metaFieldCall = ShopController::shopify_rest_call($store->shopify_token, $request->shop, $api_endpoint, $solcoders_metafields, 'POST');
            }
            else{
                $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', "2023-01") . '/metafields.json';
                $solcoders_metafields = [
                    "metafield" => ["namespace" => "solcoders", "key" => "specific_products", "value" => "all", "type" => "multi_line_text_field"]
                ];
                $metaFieldCall = ShopController::shopify_rest_call($store->shopify_token, $request->shop, $api_endpoint, $solcoders_metafields, 'POST');
            }
        }
        else{
            $setting['product_feature_enable'] = 2;

            $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', "2023-01") . '/metafields.json';
            $solcoders_metafields = [
                "metafield" => ["namespace" => "solcoders", "key" => "specific_products", "value" => "null", "type" => "multi_line_text_field"]
            ];
            $metaFieldCall = ShopController::shopify_rest_call($store->shopify_token, $request->shop, $api_endpoint, $solcoders_metafields, 'POST');
        }
























        if(isset($data['btn_styling_enable']) && $data['btn_styling_enable'] == 1){
            $setting['btn_styling_enable'] = 1;
            $setting['btn_bg'] = $data['btn_bg'];
            $setting['btn_color'] = $data['btn_color'];
            $setting['btn_text'] = $data['btn_text'];
        }
        else{
            $setting['btn_styling_enable'] = 2;

        }

        $themes = VariantAppController::getThemes($shop);
        $theme_id = "";
        foreach($themes as $theme){
            if($theme->role == "main"){
                $theme_id = $theme->id;
            }
        }
        $filename = 'sections/main-product.liquid';
        $file = VariantAppController::getAsset($shop, $theme_id, $filename);

        if(!empty($file)){
            $updatedValue = $file->value;
            if ($filename == 'sections/main-product.liquid') {

                if($setting['product_feature_enable'] == 1){
                    if (!str_contains($updatedValue, "{%comment%} adpts_start {%- endcomment -%}")) {
                        $updatedValue = str_replace("{%- when 'title' -%}","{%- when 'title' -%}\n             {%comment%} adpts_start {%- endcomment -%}\n             {% capture adpts_specific_products %} {% if shop.metafields.solcoders.specific_products and  shop.metafields.solcoders.specific_products != 'null' %} {{ shop.metafields.solcoders.specific_products }} {% else %} \"\" {% endif %} {% endcapture %}\n             {%- assign adpts_condition = false  -%}\n                 {%- if adpts_specific_products contains product.id or adpts_specific_products contains \"all\" and product.variants.size > 1 -%}\n                        {%- assign adpts_condition = false  -%}\n                 {% else %}\n                    {%- assign adpts_condition = true  -%}\n                {% endif %}\n",$updatedValue);
                        $updatedValue = str_replace("{%- when 'quantity_selector' -%}","{%- when 'quantity_selector' -%}\n             {%- if adpts_condition -%}\n",$updatedValue);
                        $updatedValue = str_replace("{%- when 'popup' -%}","{% endif %}\n            {%- when 'popup' -%}",$updatedValue);
                        $updatedValue = str_replace("{%- when 'variant_picker' -%}","{%- when 'variant_picker' -%}\n              {% if adpts_condition %}",$updatedValue);
                        $updatedValue = str_replace("{%- when 'buy_buttons' -%}","{% endif %}\n            {%- when 'buy_buttons' -%}\n            {% if adpts_condition %}",$updatedValue);
                        $updatedValue = str_replace("{%- when 'rating' -%}","{% endif %}\n            {%- when 'rating' -%}",$updatedValue);
                    }
                }
                else{
                    if (str_contains($updatedValue, "{%comment%} adpts_start {%- endcomment -%}")) {
                        $updatedValue = str_replace("{%- when 'title' -%}\n             {%comment%} adpts_start {%- endcomment -%}\n             {% capture adpts_specific_products %} {% if shop.metafields.solcoders.specific_products and  shop.metafields.solcoders.specific_products != 'null' %} {{ shop.metafields.solcoders.specific_products }} {% else %} \"\" {% endif %} {% endcapture %}\n             {%- assign adpts_condition = false  -%}\n                 {%- if adpts_specific_products contains product.id or adpts_specific_products contains \"all\" and product.variants.size > 1 -%}\n                        {%- assign adpts_condition = false  -%}\n                 {% else %}\n                    {%- assign adpts_condition = true  -%}\n                {% endif %}\n","{%- when 'title' -%}",$updatedValue);
                        $updatedValue = str_replace("{%- when 'quantity_selector' -%}\n             {%- if adpts_condition -%}\n","{%- when 'quantity_selector' -%}",$updatedValue);
                        $updatedValue = str_replace("{% endif %}\n            {%- when 'popup' -%}","{%- when 'popup' -%}",$updatedValue);
                        $updatedValue = str_replace("{%- when 'variant_picker' -%}\n              {% if adpts_condition %}","{%- when 'variant_picker' -%}",$updatedValue);
                        $updatedValue = str_replace("{% endif %}\n            {%- when 'buy_buttons' -%}\n            {% if adpts_condition %}","{%- when 'buy_buttons' -%}",$updatedValue);
                        $updatedValue = str_replace("{% endif %}\n            {%- when 'rating' -%}","{%- when 'rating' -%}",$updatedValue);
                    }

                }
                $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', "2023-01") . '/themes/' . $theme_id . '/assets.json';
                $asset = ['asset' => [
                    "key" => $filename,
                    "value" => $updatedValue
                ]];
                $update = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint, $asset, 'PUT');
                $update = json_decode($update['response']);
            }
        }
        $store->variant_settings = $setting;
        $store->update();
        $previousUrl = app('url')->previous();
        return redirect()->to($previousUrl.'&'. http_build_query(['page'=>'VariantTable']));
    }

    public static function getThemes($shop)
    {
        $store = Store::where('shop_url', $shop)->first();

        if (!empty($store->shopify_token)) {
            $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', "2023-01") . '/themes.json';
            $themes = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint, array(), 'GET');
            $themes = json_decode($themes['response']);
            $themes = $themes->themes;
            return $themes;
        } else {
            return response(['Store not Found']);
        }
    }

    public static function getAsset($shop, $theme_id, $filename)
    {
        $store = Store::where('shop_url', $shop)->first();

        if (!empty($store->shopify_token)) {
            $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', '2023-01') . '/themes/' . $theme_id . '/assets.json?asset[key]=' . $filename . '&theme_id=' . $theme_id;


            $assets = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint, array(), 'GET');
            $assets = json_decode($assets['response']);
            if(isset($assets)){
                if(!empty($assets->asset)){
                    $assets = $assets->asset;
                }
                else{
                    $assets = "";
                }
            }
            else{
                $assets = "";
            }
            return $assets;
        } else {
            return response(['Store not Found']);
        }
    }
}
