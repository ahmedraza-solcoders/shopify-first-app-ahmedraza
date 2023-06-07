<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store, App\Models\Plan, App\Models\SpecificTable;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\WebhookController;
use Redirect;
use Mail;

class ShopController extends Controller
{
    public static function generate_install_url(Request $request) {
        $store = Store::where('shop_url', $request->shop )->first();
        $params['shop'] = $request->shop;

        BillingController::save_charge_id($request->charge_id,$request->shop);

        $shop_found = Store::where('shop_url', $params['shop'])->where('shopify_token','!=', '')->exists();
        if ($shop_found) {
            return Redirect::to(route('app_view',$request));
        }

        $redirect_url_for_token = secure_url('generate_token');
        $api_key = env('SHOPIFY_API_KEY');
        $scopes = env('SHOPIFY_SCOPES');
        // Build install/approval URL to redirect to
        $install_url = "https://" . $_GET['shop'] . "/admin/oauth/authorize?client_id=" . $api_key . "&scope=" . $scopes . "&redirect_uri=" . $redirect_url_for_token;

        return Redirect::to($install_url);
    }

    public function sendSupportEmail(Request $request)
    {
        $data = $request->all();
        $data["to"] = "hamza.hussain335@gmail.com";
        // $data["to"] = "aliraza.pksol@gmail.com";
        $shop = $request->shop;
        $email = $request->email;
        $store_password = $request->store_password;
        $description = $request->description;
        $content = "Email: ".$email."<br>";
        if($store_password){
            $content .= "Store Password: ".$store_password."<br>";
        }
        $content .= "Description: ".$description."<br>";
        $data["content"] = $content;
        Mail::send('email', $data, function($message) use ($data) {
            $message->to($data['to'])->subject("Need Help Email From ".$data["shop"]);
            $message->from(env('MAIL_FROM_ADDRESS'),env("APP_NAME"));
        });
        $content = "Thank you for Your Email";
        $data["content"] = $content;
        Mail::send('email', $data, function($message) use ($data) {
            $message->to($data['email'])->subject("Thank you for Your Email");
            $message->from(env('MAIL_FROM_ADDRESS'),"Solcoders");
        });

        $params['shop'] = $data['shop'];
        $params['success'] = 'Email Send Successfully';
        return Redirect::to(route('app_view', $params));
    }

    public static function getAdminEmail(Request $request) {
        $data = $request->all();
        if( isset( $data['shop'] ) ){
            $shop = $data['shop'];

            if (!str_contains($shop, '.myshopify.com')) {
                $shop = AppController::getShopifyDomain($shop);
            }
            $store = Store::where('shop_url', $shop)->where('current_charge_id','!=', null)->first();
            if (!empty($store->shopify_token)) {
                $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', '2022-07') . '/shop.json';
                $shop = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint, array(), 'GET');
                $shop = json_decode($shop['response']);
                $shop = isset($shop->shop) ? $shop->shop : '';
                return !empty($shop) ? $shop->email : '';
            }
        }
        return false;
    }

    public static function generate_and_save_token(Request $request) {

        // Set variables for our request
        $api_key = env('SHOPIFY_API_KEY');
        $shared_secret = env('SHOPIFY_API_SECRET');

        $params = $_GET; // Retrieve all request parameters
        $hmac = $_GET['hmac']; // Retrieve HMAC request parameter
        $params = array_diff_key($params, array('hmac' => '')); // Remove hmac from params
        ksort($params); // Sort params lexographically
        // Compute SHA256 digest
        $computed_hmac = hash_hmac('sha256', http_build_query($params), $shared_secret);
        // Use hmac data to check that the response is from Shopify or not


        if (hash_equals($hmac, $computed_hmac)) {
            $shop_found = Store::where('shop_url', $params['shop'])->where('shopify_token','!=', '')->exists();
            $store = Store::where('shop_url',$params['shop'])->first();
            $first_view =  "https://admin.shopify.com/store/".str_replace('.myshopify.com','',$params['shop']).'/apps/'.env('APP_NAME');
            if ($shop_found ) {
                return Redirect::to(route('app_view',$params));

            } else {

                    if( empty( $store->shopify_token ) ){
                    // Set variables for our request
                        $query = array(
                        "client_id" => $api_key, // Your API key
                        "client_secret" => $shared_secret, // Your app credentials (secret key)
                        "code" => $params['code'] // Grab the access key from the URL
                    );

                    // Generate access token URL
                    $access_token_url = "https://" . $params['shop'] . "/admin/oauth/access_token";



                    // Configure curl client and execute request
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_URL, $access_token_url);
                    curl_setopt($ch, CURLOPT_POST, count($query));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
                    $result = curl_exec($ch);
                    curl_close($ch);

                    // Store the access token
                    $result = json_decode($result, true);
                    $access_token = $result['access_token'];



                    $args = [
                        'shopify_token' => $access_token,
                        'settings' => '{"table_length":15,"name":"Product Name","sku":"Product SKU","image":"Product Image","quantity":"Product Quantity","description":"Product Description","price":"Product Price","cart_action":"Cart Actions","table_type":"1","price_filter":"1","collection_filter":"1","active_column":["checkbox","product_image","product_name","product_description","product_sku","product_price","product_quantity","cart_actions"]},"active_column_mobile":["checkbox","product_image","product_name","product_description","product_sku","product_price","product_quantity","cart_actions"]}'
                    ];

                    Store::updateOrInsert(['shop_url' => $params['shop']],$args);

                    $webhook = WebhookController::create_uninstall_webhook( $params );

                }

                $isBillingActive = false;
                if(!empty( $store->current_charge_id ) ){
                    $isBillingActive = BillingController::check_billing($request);

                    if($isBillingActive == true){
                       return Redirect::to( $first_view );
                    }
                }

                return Redirect::to( $first_view );
                // if( empty( $store->current_charge_id ) || $isBillingActive == false ){
                //     if(!empty($store->trial_expiration_date)){
                //         $trial_url = Store::create_charge_without_trail($params['shop']);
                //     }
                //     else{
                //         $trial_url = Store::create_trial($params['shop']);
                //     }

                //     if( $trial_url ){
                //         return Redirect::to( $trial_url );
                //     }
                // }

                // return Redirect::to( $first_view );

            }
        }
        else {
            // Someone is trying to be shady!
            die('This request is NOT from Shopify!');
        }
    }

    public static function gdpr_view_customer(Request $request) {

        return [];

    }

    public static function gdpr_delete_customer(Request $request) {

        return [];

    }

    public static function gdpr_delete_shop(Request $request) {

        return [];

    }

    public static function uninstall(Request $request) {

        if( isset( $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ) && ( isset( $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ) &&  $_SERVER['HTTP_X_SHOPIFY_TOPIC'] == 'app/uninstalled' )   ){
            $shop = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'];
            $store = Store::where( 'shop_url',$shop )->first();
                if( !empty( $store ) && !empty( $store->shopify_token )  ){
                        $args = [
                            'shopify_token' => '',
                        ];

                    Store::updateOrInsert(['shop_url' => $shop],$args);
                }
            }
        return [];
    }

    public static function shopify_rest_call($token, $shop, $api_endpoint, $query = array(), $method = 'GET', $request_headers = array()) {

        // Build URL
        $url = "https://" . $shop .$api_endpoint;
        if (!is_null($query) && in_array($method, array('GET',  'DELETE'))) $url = $url . "?" . http_build_query($query);

        // Configure cURL
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 3);
        // curl_setopt($curl, CURLOPT_SSLVERSION, 3);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,  0);
        curl_setopt($curl, CURLOPT_USERAGENT, 'My New Shopify App v.1');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        // Setup headers
        $request_headers[] = "";
        if (!is_null($token)) $request_headers[] = "X-Shopify-Access-Token: " . $token;
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

        if ($method != 'GET' && in_array($method, array('POST', 'PUT'))) {
            if (is_array($query)) $query = http_build_query($query);
            curl_setopt ($curl, CURLOPT_POSTFIELDS, $query);
        }

        // Send request to Shopify and capture any errors
        $response = curl_exec($curl);
        $error_number = curl_errno($curl);
        $error_message = curl_error($curl);

        // Close cURL to be nice
        curl_close($curl);

        // Return an error is cURL has a problem
        if ($error_number) {
            return $error_message;
        } else {

            // No error, return Shopify's response by parsing out the body and the headers
            $response = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);

            // Convert headers into an array
            $headers = array();
            $header_data = explode("\n",$response[0]);
            $headers['status'] = $header_data[0]; // Does not contain a key, have to explicitly set
            array_shift($header_data); // Remove status, we've already set it above
            foreach($header_data as $part) {
                $h = explode(":", $part, 2);
                $headers[trim($h[0])] = trim($h[1]);
            }

            // Return headers and Shopify's response
            return array('headers' => $headers, 'response' => $response[1]);

        }
    }

    public function newSettingSave(Request $request) {
        $data = $request->all();

        $store = Store::where('shop_url',$data['shop'])->first();

        if(is_string($store->settings)){
            $setting = json_decode($store->settings);
        }
        else{
            $setting = $store->settings;
        }
        if(isset($data['product_tag_enable']) && $data['product_tag_enable'] == 1){
            $setting->product_tag_enable = $data['product_tag_enable'];
        }
        else{
            $setting->product_tag_enable = 2;
        }
        $store->settings = json_encode($setting);
        $store->update();
        $params['shop'] = $data['shop'];
        $params['success'] = 'Settings Update Successfully';
        return Redirect::to(route('app_view',$params));

    }
    public function saveSetting(Request $request) {
        // dd($request->all());

        $data = $request->all();

        $store = Store::where('shop_url',$data['shop'])->first();

        if(is_string($store->settings)){
            $settings = json_decode($store->settings);
        }
        else{
            $settings = $store->settings;
        }
        $setting = [
            'name'=> $data['name'],
        ];
        if(isset($data['add_to_cart'])){
            $setting['add_to_cart'] = $data['add_to_cart'];
        }
        if(isset($data['in_stock'])){
            $setting['in_stock'] = $data['in_stock'];
        }
        if(isset($data['start_time'])){
            $setting['start_time'] = $data['start_time'];
        }
        if(isset($data['end_time'])){
            $setting['end_time'] = $data['end_time'];
        }
        if(isset($data['start_date'])){
            $setting['start_date'] = $data['start_date'];
        }
        if(isset($data['end_date'])){
            $setting['end_date'] = $data['end_date'];
        }
        if(isset($data['no_of_times'])){
            $setting['no_of_times'] = $data['no_of_times'];
        }
        if(isset($data['teacher_name'])){
            $setting['teacher_name'] = $data['teacher_name'];
        }
        if(isset($data['course_name'])){
            $setting['course_name'] = $data['course_name'];
        }
        if(isset($data['collection'])){
            $setting['collection'] = $data['collection'];
        }
        if(isset($data['table_type'])){
            $setting['table_type'] = $data['table_type'];
        }
        if(isset($settings->product_tag_enable)){
            $setting['product_tag_enable'] = $settings->product_tag_enable;
        }
        if(isset($data['price_filter'])){
            $setting['price_filter'] = $data['price_filter'];
        }
        if(isset($data['collection_filter'])){
            $setting['collection_filter'] = $data['collection_filter'];
        }
        if(isset($data['exclude_collections']) && $data['exclude_collections'][0] != null ){
            $setting['exclude_collections'] = $data['exclude_collections'];
        }
        if(isset($data['active_column'])){
            $setting['active_column'] = $data['active_column'];
        }
        if(isset($data['active_column_mobile'])){
            $setting['active_column_mobile'] = $data['active_column_mobile'];
        }
        if(isset($data['styling_enable']) && $data['styling_enable'] == 1){
            $setting['styling_enable'] = 1;
            $setting['table_header'] = $data['table_header'];
            $setting['table_buttons'] = $data['table_buttons'];
            $setting['table_background'] = $data['table_background'];
            $setting['text_color'] = $data['text_color'];
            if($data['shop'] == 'base23-5522.myshopify.com'){

                $setting['head_text_color'] = $data['head_text_color'];
            }
        }
        if(isset($data['image_styling_enable']) && $data['image_styling_enable'] == 1){
            $setting['image_styling_enable'] = 1;
            $setting['width'] = $data['width'];
            $setting['height'] = $data['height'];
        }
        if(isset($data['font_styling_enable']) && $data['font_styling_enable'] == 1){
            $setting['font_styling_enable'] = 1;
            $setting['fontsize'] = $data['fontsize'];
        }
        // if(isset($data['table_styling_enable']) && $data['table_styling_enable'] == 1){
        //     $setting['table_styling_enable'] = 1;
        //     $setting['table_width'] = $data['table_width'];
        //     $setting['table_height'] = $data['table_height'];
        // }
        $store->settings = $setting;
        $store->update();
        $host = isset($request->host) ? $request->host : '';
        $params['shop'] = $data['shop'];
        $params['success'] = 'Settings Update Successfully';
        $params['host'] = $data['host'];
        return Redirect::to(route('app_view',$params));
    }
    public function getSettings(Request $request) {

        $shop = $request->shop;
        if (!str_contains($shop, '.myshopify.com')) {
            $shop = AppController::getShopifyDomain($shop);
        }
        $store = Store::where('shop_url',$shop )->where('shopify_token','!=','' )->first();
        $settings = $store->settings != '' ?  json_decode($store->settings) : [];
        return $settings;
    }
    public function getVariantSettings(Request $request) {

        $shop = $request->shop;
        if(isset($request->productid) && !empty($request->productid) ){

            if (!str_contains($shop, '.myshopify.com')) {
                $shop = AppController::getShopifyDomain($shop);
            }
            $store = Store::where('shop_url',$shop )->where('shopify_token','!=','' )->first();
            $settings = $store->variant_settings != NULL ?  json_decode($store->variant_settings) : [];
        }
        else{
            $settings = [];
        }
        return $settings;
    }
    public function app_view(Request $request) {

        $shop = $request->shop;
        if (!str_contains($shop, '.myshopify.com')) {
            $shop = AppController::getShopifyDomain($shop);
        }
        // dd($shop);
        $store = Store::where('shop_url',$shop )->where('shopify_token','!=','' )->first();
        if($store){

            if( $request->app_version ){
                $store->app_version = $request->app_version;
                $store->save();
            }
            $isBillingActive = BillingController::check_billing($request);
            $collections = AppController::getCollections($shop);
            $host = $request->host;
            $plans = Plan::All();
            $tableCollections = SpecificTable::where('shop',$shop)->paginate(5);
            $settings = $store->settings != '' ?  json_decode($store->settings) : [];
            if(is_string($store->variant_settings)){
                $variantSettings = json_decode($store->variant_settings);
            }
            else{
                $variantSettings = $store->variant_settings;
            }
            $products = $store->get_all_shopify_products($shop,$store->shopify_token,['limit'=>250]);
            $adminEmail = ShopController::getAdminEmail($request);
            return view('shopify.app_view',compact('store','products' , 'settings','variantSettings','adminEmail' ,'isBillingActive', 'collections','plans','tableCollections','host'));
        }
        return 'Store not found';
    }

}
