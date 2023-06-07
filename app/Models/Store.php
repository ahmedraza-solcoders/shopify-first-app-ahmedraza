<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Cookie;
use Session;

class Store extends Model
{

    use HasFactory;
    protected $table = 'shopify_stores_data';
    protected $fillable = [
        'shop_url',
        'shopify_token',
        'is_trial_expired',
        'plan_id',
        'current_charge_id',
        'variant_settings',
    ];

    /**
     * Get the plan associated with the store.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    /**
     * Create 3-days trial for app.
     * @param string $shop_url
     * @return string|boolean
     */
    static function create_trial( $shop_url ){

        $store = Store::where('shop_url', $shop_url )->first();
        if( $store ){
            $first_view =  "https://".$shop_url.'/admin/apps/'.env('APP_NAME');
            $plan = Plan::get_default_plan_id();
            $array = [
                "recurring_application_charge" => [
                    "name" => $plan->name,
                    "price" => $plan->price,
                    "return_url" => $first_view,
                    "test" => env('PAYMENT_MODE',false),
                    "trial_days" => 30
                ]
            ];

            $charge = ShopController::shopify_rest_call($store->shopify_token, $shop_url, '/admin/api/'.env('SHOPIFY_API_VERSION','2022-07').'/recurring_application_charges.json', $array, 'POST');

            $result = json_decode($charge['response'], JSON_PRETTY_PRINT);
            // dd($result['recurring_application_charge']['']);
            $confirmation_url = $result['recurring_application_charge']['confirmation_url'];


            return $confirmation_url;

        }
    }

    /**
     * Create charge for app.
     * @param string $shop_url
     * @return string|boolean
     */
    static function create_charge_without_trail( $shop_url ){

        $store = Store::where('shop_url', $shop_url )->first();
        if( $store ){
            $first_view =  "https://".$shop_url.'/admin/apps/'.env('APP_NAME');
            $array = [
                "recurring_application_charge" => [
                    "name" => env('PLAN_NAME'),
                    "price" => env('PLAN_PRICE'),
                    "return_url" => $first_view,
                    "test" => env('PAYMENT_MODE',false)
                ]
            ];

            $charge = ShopController::shopify_rest_call($store->shopify_token, $shop_url, '/admin/api/'.env('SHOPIFY_API_VERSION','2022-07').'/recurring_application_charges.json', $array, 'POST');
            // dd($charge);
            $result = json_decode($charge['response']);
            // return $result->recurring_application_charge;
            $confirmation_url = $result->recurring_application_charge->confirmation_url;


            return $confirmation_url;

        }
    }

    /**
     * Cancel charge for app.
     * @param string $shop_url
     * @return string|boolean
     */
    static function cancel_charge( $shop_url ){

        $store = Store::where('shop_url', $shop_url )->first();
        if( $store ){
            $first_view =  "https://".$shop_url.'/admin/apps/'.env('APP_NAME');

            $charge = ShopController::shopify_rest_call($store->shopify_token, $shop_url, '/admin/api/'.env('SHOPIFY_API_VERSION','2022-07').'/recurring_application_charges/'.$store->current_charge_id.'.json', [], 'DELETE');
            $first_view =  "https://".$shop_url.'/admin/apps/'.env('APP_NAME');
            return $first_view;

        }
    }
    /**
     * Get all products from the shopify store.
     * @param array $args
     * @return array
     */
    public function get_all_shopify_products($shop_url, $shopify_token, $args = []){
        \Shopify\Context::initialize(
                env('SHOPIFY_API_KEY'),
                env('SHOPIFY_API_SECRET'),
                'read_products,read_inventory',
                $shop_url,
                new \Shopify\Auth\FileSessionStorage( storage_path()),
                env('SHOPIFY_API_VERSION'),
                true,
                false
            );
        $client = new \Shopify\Clients\Rest($shop_url, $shopify_token);
        try{
            $product_response = $client->get('products', [], $args );
            $product_response->getBody()->rewind();
            $responseBody = $product_response->getBody()->getContents();
            $body = json_decode($responseBody);
            $products = [];
            if( isset($body->products)){
                $products = $body->products;
                $this->get_paginated_data($client,$product_response,$products);
            }else{
                // _log(  print_r($body,1), 'api_connection_issue');
            }
            return $products;


        }catch(\Exception $e ){
            \Log::info(  $e->getMessage());
            return [];
        }
    }
    /**
     * Get products from the shopify store.
     * @param array $args
     * @return array
     */
    public function get_shopify_products($request,$shop_url, $shopify_token, $args = [], $query,$nextPageInfo = NULL){
        \Shopify\Context::initialize(
                env('SHOPIFY_API_KEY'),
                env('SHOPIFY_API_SECRET'),
                'read_products,read_inventory',
                $shop_url,
                new \Shopify\Auth\FileSessionStorage( storage_path()),
                env('SHOPIFY_API_VERSION'),
                true,
                false
            );
        $client = new \Shopify\Clients\Rest($shop_url, $shopify_token);
        try{


            $pageInfo =  "";
            if($nextPageInfo !== null){
                if($query == 'next'){
					if(is_string($nextPageInfo)){
						$nextPageInfo = json_decode($nextPageInfo);
					}

                    if($nextPageInfo->next !== NULL){
                        $args = (array) $nextPageInfo->next;
                    }
                }elseif($query == 'prev'){

					if(is_string($nextPageInfo)){
						$nextPageInfo = json_decode($nextPageInfo);
					}
                    if($nextPageInfo->prev !== NULL){
                        $args = (array) $nextPageInfo->prev;
                    }
                }
            }
            $product_response = $client->get('products', [], $args );
            $product_response->getBody()->rewind();

			$responseBody = $product_response->getBody()->getContents();

			$body = json_decode($responseBody);


            $products = [];
            if( isset($body->products)){
                $products = $body->products;


                if( !empty($product_response->getPageInfo()) ){

                    $serialized_page_info = serialize($product_response->getPageInfo());
                    $page_info = unserialize($serialized_page_info);

                    if( $page_info ){
                        $pageInfo = $page_info;
                    }
                    else{

                        $pageInfo = NULL;
                    }
                }else{
                    $pageInfo = NULL;
                }
            }else{
                // _log(  print_r($body,1), 'api_connection_issue');
            }

            return ["products"=>$products,"pageInfo"=>$pageInfo];


        }catch(\Exception $e ){
            \Log::info(  $e->getMessage());
            return [];
        }
    }

    public function get_paginated_data( $client, $result, &$products = [] ){

        if( empty($result->getPageInfo()) ){
            return $products;
        }
        $serialized_page_info = serialize($result->getPageInfo());
        $page_info = unserialize($serialized_page_info);

        if( !$page_info->hasNextPage() ){
            return $products;
        }

        try{
            $product_response = $client->get('products', [], $page_info->getNextPageQuery() );
            $product_response->getBody()->rewind();
            $responseBody = $product_response->getBody()->getContents();
            $body = json_decode($responseBody);
            if( isset($body->products)){
                $paginated_products = $body->products;
                $products           = array_merge($products,$paginated_products);
                $this->rest_client = $client;
                $this->product_response = $product_response;
                sleep(1);
                $this->get_paginated_data($client, $product_response, $products);
            }else{
                \Log::info(  print_r($body,1));
            }
            return $products;


        }catch(\Exception $e ){
            \Log::info($e->getMessage());
            return [];
        }
    }



}
