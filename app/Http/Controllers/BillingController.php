<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store, App\Models\Plan;
use Redirect;
class BillingController extends Controller
{

    public static function save_charge_id($charge_id,$shop) {
        $store = Store::where('shop_url', $shop )->first();

        if( isset( $charge_id ) && $store->current_charge_id != $charge_id ){
            $status = BillingController::check_billing_charge_id($shop,$charge_id);

            if( $store->trial_expiration_date == null){
                $date = \Carbon\Carbon::now();
                $date->addDays(3);
                $date->format("Y-m-d h:i:s");
                $args['trial_expiration_date'] = $date;
            }

            
            if($status){

                $args['current_charge_id'] = $charge_id;
            }
            else{
                $args['current_charge_id'] = $store->current_charge_id;
            }


            if( !empty( $args ) ){
                Store::updateOrInsert(['shop_url' => $shop],$args);
                $store = Store::where('shop_url', $shop )->first();
            }
        }
    }

    public static function check_billing_charge_id($shop,$charge_id) {


        if (!str_contains($shop, '.myshopify.com')) {
            $shop = AppController::getShopifyDomain($shop);
        }
        $store = Store::where('shop_url', $shop)->where('current_charge_id','!=', null)->first();
        if(!empty($store)){

            $url = 'https://'.$shop.'/admin/api/'.env('SHOPIFY_API_VERSION','2022-07').'/recurring_application_charges/'.$charge_id.'.json';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $headers = array();
            $headers[] = 'X-Shopify-Access-Token: '.$store->shopify_token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
            }
            curl_close($ch);
            $response = json_decode($result);

            if(isset($response->errors) ){

                return false;
            }
            if(isset($response) && $response->recurring_application_charge->status == 'active'){
                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
        }

        return false;
    }

    public static function check_billing(Request $request) {
        $data = $request->all();
        if( isset( $data['shop'] ) ){
            $shop = $data['shop'];

            if (!str_contains($shop, '.myshopify.com')) {
                $shop = AppController::getShopifyDomain($shop);
            }
            $store = Store::where('shop_url', $shop)->where('current_charge_id','!=', null)->first();
            if(!empty($store)){

                $url = 'https://'.$shop.'/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/recurring_application_charges/'.$store->current_charge_id.'.json';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

                $headers = array();
                $headers[] = 'X-Shopify-Access-Token: '.$store->shopify_token;
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                    echo 'Error:' . curl_error($ch);
                }
                curl_close($ch);
                $response = json_decode($result);

                if(isset($response->errors) ){

                    return ['billing'=>false];
                }
                if(isset($response) && $response->recurring_application_charge->status == 'active'){

					return ['billing'=>true];
                }
                else
                {
                    return ['billing'=>false];
                }
        }
        else
        {
            return ['billing'=>false];
        }
        }
        return ['billing'=>false];
    }

    public static function checkCurrentChargeStatus(Request $request) {
        $data = $request->all();
        $shop = $data['shop'];
        $store = Store::where('shop_url', $shop)->first();


        $url = 'https://'.$shop.'/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/recurring_application_charges/'.$store->current_charge_id.'.json';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = array();
        $headers[] = 'X-Shopify-Access-Token: '.$store->shopify_token;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        $response = json_decode($result);
        return $response;
    }

    public function cancelCharge(Request $request)
    {
        $data = $request->all();

        $store = Store::where('shop_url',$data['shop'])->first();
        $return = Store::cancel_charge($data['shop']);
        // dd($return);
        return $return;
    }

    public function createCharge(Request $request)
    {
        $data = $request->all();

        $store = Store::where('shop_url',$data['shop'])->first();
        // return $store;
        if(isset($store->trial_expiration_date)){
            $return = Store::create_charge_without_trail($data['shop']);
        }
        else{
            $return = Store::create_trial($data['shop']);
        }
        return $return;
    }


    /**
     * Create charge url.
     * @param int $plan_id
     * @param Request $request
     * @return Response
     */
    public function create_charge( $plan_id , Request $request){

        $store = Store::where('shop_url',$request->shop )->first();
        $plan = Plan::find( $plan_id );
        $return_url = route( 'plan.change', ['id'=> $plan->id, 'shop' => $request->shop]);

        /**
         * Free Plan
         */
        if( $plan->price < 0.5){
            $store->cancel_recurring_charge();
            return response( [
                'success' => true,
                'response' => [
                    'recurring_application_charge' => [
                        'confirmation_url' => $return_url
                    ]
                ]
            ]);
        }

        \Shopify\Context::initialize(
            env('SHOPIFY_API_KEY'),
            env('SHOPIFY_API_SECRET'),
            env('SHOPIFY_SCOPES'),
            $request->shop,
            new \Shopify\Auth\FileSessionStorage( storage_path()),
            env('SHOPIFY_API_VERSION'),
            true,
            false
        );
        $client = new \Shopify\Clients\Rest( $request->shop, $store->shopify_token);
        try{
            if($request->is_trial != 'false'){
                $response = $client->post(
                    "recurring_application_charges",
                    [
                        "recurring_application_charge" => [
                            "name" => $plan->name,
                            "price" => $plan->price,
                            "return_url" => $return_url,
                            "test" => env('PAYMENT_MODE',false),
                            "trial_days" => 30
                        ]
                    ]
                );
            }
            else{

                $response = $client->post(
                    "recurring_application_charges",
                    [
                        "recurring_application_charge" => [
                            "name" => $plan->name,
                            "price" => $plan->price,
                            "return_url" => $return_url,
                            "test" => env('PAYMENT_MODE',false),
                        ]
                    ]
                );

            }
            $result = $response->getDecodedBody();

                /**
                 * Create created successfully.
                 */
                return response(['success'=>true, 'response'=> $response->getDecodedBody() ], 200);

        }catch(\Exception $e ){
            _log(  $e->getMessage(), 'api_connection_issue');

            /**
             * Error while creating charge.
             */
            return response(['error'=> 'Cannot create charge.'], 400);
        }
    }
    /**
     * Change store plan
     * @param int $id
     * @param Request $request
     * @return type
     */
    public function change_plan( $id, Request $request){

        $store = Store::where('shop_url',$request->shop )->first();

        if($store ){
            if( !empty($request->charge_id) ){
                $store->current_charge_id = $request->charge_id;
            }else{
                $store->current_charge_id = null;
            }
            $store->trial_expiration_date = date('Y-m-d h:i:s');
            $store->plan_id = $id;
            $store->update();
            $first_view =  "https://".$request->shop.'/admin/apps/'.env('APP_NAME');
            return Redirect::to($first_view);
            return Redirect::to(route('admin',['shop'=>$request->shop, 'success'=>'Plan changed successfully']));

        }
    }
}
