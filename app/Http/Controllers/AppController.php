<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ShopController;
use App\Models\Store, App\Models\SpecificTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DataTables;
use Carbon\Carbon;

class AppController extends Controller
{

    public static function getShopifyDomain($url)
    {
        $response = Http::get("https://".$url);
        $string = $response->body();
        $explode = explode('Shopify.shop = "',$string);
        $explode = explode('Shopify.locale = "',$explode[1]);
        $shop=str_replace("\";\n","",$explode[0]);
        return $shop;
    }
    public static function tableView(Request $request ) {
        $shop = $request->shop;
        $store = Store::where('shop_url', $shop)->first();
        $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/shop.json';
        $shopifyShop = ShopController::shopify_rest_call($store->shopify_token, $request->shop, $api_endpoint , array() ,'GET');
        $shopifyShop = json_decode($shopifyShop['response']);
        $shopifyShop = $shopifyShop->shop;
        //dd($shopifyShop);

        $currency = $shopifyShop->currency;
        if(is_string($store->settings)){
            $settings = json_decode($store->settings);
        }
        else{
            $settings = $store->settings;
        }
        $product_image = in_array("product_image", $settings->active_column) ? true : false;
        $product_name = in_array("product_name", $settings->active_column) ? true : false;
        $product_description = in_array("product_description", $settings->active_column) ? true : false;
        $product_sku = in_array("product_sku", $settings->active_column) ? true : false;
        $product_quantity = in_array("product_quantity", $settings->active_column) ? true : false;
        $product_price = in_array("product_price", $settings->active_column) ? true : false;
        $cart_actions = in_array("cart_actions", $settings->active_column) ? true : false;



        return view('table-view',compact("settings","product_image", "product_name", "product_description", "product_sku", "product_quantity", "product_price", "cart_actions", 'currency'));
    }

    public function getProductVariantsAjax(Request $request)
    {
        $store = Store::where('shop_url', $request->shop)->first();

        if( !empty( $store->shopify_token ) ){
            $settings= $store->variant_settings;
            if (is_string($settings)) {
                $settings = json_decode($store->variant_settings);
            }
            if($request->productid){
                    // dd($settings);
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
                                $image = env('APP_URL'). 'public/images/white-image.png';
                            }
                            $html .= '<tr>';
                            if($name){
                                $html .= "<td>";
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
    public function getProductsAjax(Request $request)
    {


        $shop = $request->shop;
        if (!str_contains($shop, '.myshopify.com')) {
           $shop = AppController::getShopifyDomain($shop);
        }
        $store = Store::where('shop_url', $shop)->first();

        if($store->plan_id == 2 && empty($productid)){
            $page_url = $request->page_url;

            $specificTable = SpecificTable::where('url', $page_url)->where('shop', $shop)->orderBy('id','DESC')->first();
            if($specificTable){
                $get_collection = $specificTable->collection_id;
            }
            else
            {
                $get_collection = "";
            }
        }
        else
        {
            $get_collection = "";
        }
        $pageInfo = $request->pageInfo;

        if( !empty( $store->shopify_token ) ){
            if(is_string($store->settings)){
                $settings = json_decode($store->settings);
            }
            else{
                $settings = $store->settings;
            }
            if(isset($settings->table_length)){
                $tableLength = $settings->table_length;
            }
            else{
                $tableLength = 15;
            }
            if(isset($settings->desc_length)){
                $descLength = $settings->desc_length;
            }
            else{
                $descLength = 100;
            }
            $excludeProducts = [];
            if(isset($settings->exclude_collections)){
                foreach($settings->exclude_collections as $collection_id){
                    $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$collection_id.'/products.json';
                    $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                    $products = json_decode($products['response']);
                    if(isset($products->products)){

                        $products = $products->products;
                        foreach($products as $product){
                            $excludeProducts[] = $product->id;

                        }
                    }
                }
            }

            if(isset($request->collection_id) || (isset($get_collection) && !empty($get_collection)) || isset($request->min_price) || isset($request->max_price)){

                $collection_id = isset($request->collection_id) ? $request->collection_id : "" ;
                $min_price = isset($request->min_price) ? $request->min_price : "" ;
                $max_price = isset($request->max_price) ? $request->max_price : "" ;


                if($collection_id != '' || $get_collection != ''){

                    $pI = [];
                    $pI['prev'] = NULL;
                    $pI['next'] = NULL;
                    if($collection_id != '' ){
                        $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$collection_id.'/products.json';
                        $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                        $products = json_decode($products['response']);

                        $products = $products->products;
                    }
                    elseif($get_collection != '' )
                    {
                        $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$get_collection.'/products.json';

                        $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                        $products = json_decode($products['response']);

                        $products = $products->products;
                        // if($min_price != '' || $max_price != ''){
                        //     $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                        //     $pageInfo = NULL;
                        //     $pI = [];
                        //     $pI['prev'] = NULL;
                        //     $pI['next'] = NULL;
                        // }
                    }

                }
                else{

                    $store = Store::where('shop_url', $shop)->first();
                    $storeModel = new Store();
                    if(is_string($store->settings)){
                        $settings = json_decode($store->settings);
                    }
                    else{
                        $settings = $store->settings;
                    }
                    $query = null;
                    if($request->next == "true"){
                        $query = 'next';
                    }
                    if($request->next == "false"){
                        $query = 'prev';
                    }
                    if($min_price != '' || $max_price != ''){

                        $products = $storeModel->get_all_shopify_products($shop,$store->shopify_token,['limit'=>250]);
                        $pageInfo = NULL;
                        $pI = [];
                        $pI['prev'] = NULL;
                        $pI['next'] = NULL;
                    }
                    else{
                        if(isset($settings->table_length)){
                            $tableLength = $settings->table_length;
                        }
                        else{
                            $tableLength = 15;
                        }

                        $productsArray = $storeModel->get_shopify_products($request,$shop,$store->shopify_token,['limit'=>250],$query,$pageInfo);
                        $products = $productsArray['products'];
                        $pageInfo = $productsArray['pageInfo'];
                        $pI = [];
                        if($pageInfo !== NULL){
                            if(isset($pageInfo->next) && $pageInfo->next !== NULL){
                                $pI['next'] = $pageInfo->next;
                            }else{
                                $pI['next'] = NULL;
                            }
                            if(isset($pageInfo->prev) && $pageInfo->prev !== NULL){
                                $pI['prev'] = $pageInfo->prev;
                            }else{
                                $pI['prev'] = NULL;
                            }
                        }
                    }

                }
                $allProducts = array();
                for ($i=0; $i < count($products); $i++) {

                    $allProducts[] = $products[$i];
                }

                $storeModel = new Store();
                $products = $storeModel->get_all_shopify_products($shop,$store->shopify_token,['limit'=>250]);

                $mainProducts = [];

                foreach($products as $product){
                    $add = true;

                    if(count($product->variants) > 1){
                        $priceArray = [];
                        foreach($product->variants as $variant){
                            $priceArray[] = $variant->price;

                        }
                        $var_min_price = min( $priceArray )  ? min( $priceArray ) : 0;

                        $var_max_price = max( $priceArray )  ? max( $priceArray ) : 0;

                        if ($min_price != "" && $max_price == "") {
                                if ($var_min_price < $min_price) {
                                    $add = false;
                                    continue;
                                }
                        } else if ($min_price == "" && $max_price != "") {
                            if ($var_max_price > $max_price) {
                                $add = false;
                                continue;
                            }
                        } else if ($min_price != "" && $max_price != "") {
                            if ($var_min_price >= $min_price && $var_max_price <= $max_price ) {
                                $add = true;
                                continue;
                            } else{
                                $add = false;
                                continue;
                            }
                        }
                    }
                    else{

                        $price_check = $product->variants[0]->price;

                        if ($min_price != "" && $max_price == "") {
                            if ($price_check < $min_price) {
                                $add = false;
                            }
                        } else if ($min_price == "" && $max_price != "") {
                            if ($price_check > $max_price) {
                                $add = false;
                            }
                        } else if ($min_price != "" && $max_price != "") {
                            if ($price_check < $min_price) {
                                $add = false;
                            } else if ($price_check > $max_price) {
                                $add = false;
                            }
                        }
                    }

                    foreach($allProducts as $aP){
                        if($aP->id == $product->id && $add == true){
                            $mainProducts[] = $product;
                        }
                    }
                }
                $products = $mainProducts;

            }
            else{


                $store = Store::where('shop_url', $shop)->first();
                $storeModel = new Store();
                if(is_string($store->settings)){
                    $settings = json_decode($store->settings);
                }
                else{
                    $settings = $store->settings;
                }
                $query = null;
                if($request->next == "true"){
                    $query = 'next';
                }
                if($request->next == "false"){
                    $query = 'prev';
                }
                //return $query;
                if(isset($settings->table_type) && $settings->table_type == 2){

                    $products = $storeModel->get_all_shopify_products($shop,$store->shopify_token,['limit'=>250]);

                    $pI = [];
                    $pI['prev'] = NULL;
                    $pI['next'] = NULL;
                }
                else{

                    if(isset($settings->table_length)){
                        $tableLength = $settings->table_length;
                    }
                    else{
                        $tableLength = 15;
                    }
                    $productsArray = $storeModel->get_shopify_products($request,$shop,$store->shopify_token,['limit'=>$tableLength],$query,$pageInfo);

                    $products = $productsArray['products'];
                    $pageInfo = $productsArray['pageInfo'];

                    $pI = [];
                    if($pageInfo !== NULL){
                        if($pageInfo->hasNextPage()){
                            $pI['next'] = $pageInfo->getNextPageQuery();
                        }else{
                            $pI['next'] = NULL;
                        }
                        if($pageInfo->hasPreviousPage()){
                            $pI['prev'] = $pageInfo->getPreviousPageQuery();
                        }else{
                            $pI['prev'] = NULL;
                        }
                    }
                }

                $mainProducts = [];
                if(count($excludeProducts) > 0){
                    foreach($products as $product){
                        if(!in_array($product->id,$excludeProducts)){
                            $mainProducts[] = $product;
                        }
                    }
                    $products = $mainProducts;
                }

                // if(count($mainProducts) != $tableLength && $settings->table_type == 1){

                //     $length = $tableLength-count($mainProducts);
                //     if(isset($settings->table_length)){
                //         $tableLength = $settings->table_length+$length;
                //     }
                //     else{
                //         $tableLength = 15+$length;
                //     }

                //     $query = null;
                //     if($request->next == "true"){
                //         $query = 'next';
                //     }
                //     if($request->next == "false"){
                //         $query = 'prev';
                //     }
                //     $productsArray = $storeModel->get_shopify_products($request,$shop,$store->shopify_token,['limit'=>$tableLength],$query,$pageInfo);

                //     $products = $productsArray['products'];
                //     $pageInfo = $productsArray['pageInfo'];

                //     $pI = [];
                //     if($pageInfo !== NULL){
                //         if($pageInfo->hasNextPage()){
                //             $pI['next'] = $pageInfo->getNextPageQuery();
                //         }else{
                //             $pI['next'] = NULL;
                //         }
                //         if($pageInfo->hasPreviousPage()){
                //             $pI['prev'] = $pageInfo->getPreviousPageQuery();
                //         }else{
                //             $pI['prev'] = NULL;
                //         }
                //     }

                //     $mainProducts = [];

                //     if(count($excludeProducts) > 0){
                //         foreach($products as $product){
                //             if(!in_array($product->id,$excludeProducts)){
                //                 $mainProducts[] = $product;
                //             }
                //         }
                //         $products = $mainProducts;
                //     }

                // }
            }
            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/shop.json';
            $shopifyShop = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
            $shopifyShop = json_decode($shopifyShop['response']);
            $shopifyShop = $shopifyShop->shop;
            $currency = $shopifyShop->currency;
            $datatable = '';

            $checkbox = in_array("checkbox", $settings->active_column) ? true : false;
            $product_image = in_array("product_image", $settings->active_column) ? true : false;
            $product_name = in_array("product_name", $settings->active_column) ? true : false;
            $product_description = in_array("product_description", $settings->active_column) ? true : false;
            $product_sku = in_array("product_sku", $settings->active_column) ? true : false;
            $product_quantity = in_array("product_quantity", $settings->active_column) ? true : false;
            $product_price = in_array("product_price", $settings->active_column) ? true : false;
            $compare_price = in_array("compare_price", $settings->active_column) ? true : false;
            $cart_actions = in_array("cart_actions", $settings->active_column) ? true : false;
            $price_per_bottle = in_array("price_per_bottle", $settings->active_column) ? true : false;
            $b_c_rank = in_array("b_c_rank", $settings->active_column) ? true : false;
            $average_bottle_price = in_array("average_bottle_price", $settings->active_column) ? true : false;


            $checkbox_mobile = in_array("checkbox", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_image_mobile = in_array("product_image", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_name_mobile = in_array("product_name", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_description_mobile = in_array("product_description", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_sku_mobile = in_array("product_sku", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_quantity_mobile = in_array("product_quantity", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_price_mobile = in_array("product_price", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $compare_price_mobile = in_array("compare_price", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $cart_actions_mobile = in_array("cart_actions", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $price_per_bottle_mobile = in_array("price_per_bottle", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $b_c_rank_mobile = in_array("b_c_rank", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $average_bottle_price_mobile = in_array("average_bottle_price", $settings->active_column_mobile) ? '' : 'hide_on_mobile';

            foreach($products as $row){

                if ((isset($settings->product_tag_enable) && $settings->product_tag_enable == 1)   && ($shop == "product-table-33.myshopify.com" || $shop == "merrillsoft.myshopify.com" )) {
                    $productTagAvailable = false;
                    if (isset($request->customer) && $row->tags != "") {

                        $customer = explode(",",$request->customer);
                        foreach($customer as $customer){
                            $productTagAvailable = str_contains($row->tags, $customer) ? true : false;
                        }
                    }
                }
                else{
                    $productTagAvailable = true;
                }

                if($productTagAvailable){
                    $datatable .= "<tr>";
                    if(gettype($row) == "array"){
                        $row = (object) $row;
                    }

                    if ($row->variants[0]->inventory_quantity == 0 && "deny" == $row->variants[0]->inventory_policy) {
                        $html = "<td class='$checkbox_mobile'  id='cart-checkbox-" . $row->id . "'><input type='checkbox' data-id='" . $row->variants[0]->id . "' class='checkbox adpts_checkbox' id='checkbox-" . $row->id . "' disabled='true' ></td>";
                    }
                    elseif(count($row->variants) > 1 ){
                        $html = "<td class='$checkbox_mobile'  id='cart-checkbox-" . $row->id . "'><input type='checkbox' data-id='" . $row->variants[0]->id . "' class='checkbox adpts_checkbox' id='checkbox-" . $row->id . "' disabled='true' ></td>";
                    }
                    else{

                        $html = "<td class='$checkbox_mobile'  id='cart-checkbox-" . $row->id . "'><input type='checkbox' data-id='" . $row->variants[0]->id . "' class='checkbox adpts_checkbox' id='checkbox-" . $row->id . "'  ></td>";
                    }
                    if($checkbox){
                        $checkbox_html = "". mb_convert_encoding($html, 'UTF-8', 'UTF-8')."";
                    }

                    if ($row->image == null) {
                        $image = env('APP_URL'). 'public/images/white-image.png';
                    } else {
                        $image = $row->image->src;
                    }

                    if(is_string($store->settings)){
                        $settings = json_decode($store->settings);
                    }
                    else{
                        $settings = $store->settings;
                    }
                    if(isset($settings->image_styling_enable)){
                        $width = $settings->width;
                        $height = $settings->height;
                    }
                    else{
                        $width = 100;
                        $height = 100;
                    }
                    $image_html =  "<div class='text-center' id='image-" . $row->id . "'><img class='pic' width='" . $width . "' height='" . $height . "' src='" . $image . "' alt=''></div>";
                    if($product_image){
                        $product_image_html = "<td class='$product_image_mobile'>". mb_convert_encoding($image_html, 'UTF-8', 'UTF-8')."</td>";
                    }
                    if($product_name){
                        $product_name_html = "<td class='$product_name_mobile'>". mb_convert_encoding($row->title, 'UTF-8', 'UTF-8')."</td>";
                    }
                    $string = "<div>" . $row->body_html . "</div>";
                    $body_html = strip_tags($string);
                    if (strlen($body_html) > $descLength) {
                        $body_html = substr($body_html, 0, $descLength);
                        $fullbody = $string;
                        $shortbody_html = true;
                    } else {
                        $shortbody_html = false;
                        $fullbody = $body_html;
                        $body_html = $body_html;
                    }
                    $tableData = '';
                    if ($shortbody_html) {
                        $tableData .= "    <p class='m-0 text-muted'>" . $body_html . "...<a class='btn-link' data-bs-toggle='modal' data-bs-target='#exampleModal_" . $row->id . "' style='cursor: pointer !important'>Read more</a> </p>";
                        $tableData .= "        <div class='modal fade' id='exampleModal_" . $row->id . "' tabindex='-1' aria-labelledby='exampleModal_" . $row->id . "Label' aria-hidden='true'>";
                        $tableData .= "            <div class='modal-dialog modal-dialog-centered'>";
                        $tableData .= "                <div class='modal-content'>";
                        $tableData .= "                    <div class='modal-header'>";
                        $tableData .= "                        <h5 class='modal-title' id='exampleModal_" . $row->id . "Label'>Description</h5>";
                        $tableData .= "                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
                        $tableData .= "                    </div>";
                        $tableData .= "                    <div class='modal-body'>" . $fullbody . "</div>";
                        $tableData .= "                    <div class='modal-footer'>";
                        $tableData .= "                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>";
                        $tableData .= "                    </div>";
                        $tableData .= "                </div>";
                        $tableData .= "            </div>";
                        $tableData .= "        </div>";
                        // $tableData .= "    </td>";
                    } else {
                        $tableData .= "    <p class='m-0 text-muted'>" . $body_html . "</p>";
                    }
                    if($product_description){
                        $product_description_html = "<td class='$product_description_mobile'>". mb_convert_encoding($tableData, 'UTF-8', 'UTF-8')."</td>";
                    }
                    $sku = $row->variants[0]->sku ? $row->variants[0]->sku : "-";
                    if($sku != "-"){
                        $html = "<div id='sku-" . $row->id . "'><a href='https://".$shop."/products/".$row->handle."'>" . $sku . "</a></div>";
                    }
                    else{
                        $html = "<div id='sku-" . $row->id . "'>" . $sku . "</div>";
                    }
                    if($product_sku){
                        $product_sku_html = "<td class='$product_sku_mobile'>". mb_convert_encoding($html, 'UTF-8', 'UTF-8')."</td>";
                    }

                    $qty = "-";
                    if (count($row->variants) < 2) {
                        $row->id = $row->variants[0]->id;
                        $qty = "in stock";
                        if ("shopify" == $row->variants[0]->inventory_management) {
                            $qty = $row->variants[0]->inventory_quantity;
                            if ("deny" == $row->variants[0]->inventory_policy) {
                                $qty = $qty . " in stock";
                            } else if ("continue" == $row->variants[0]->inventory_policy) {
                                $qty = $qty . " in stock<br><p>Available on backorder</p>";
                            }
                        }
                    }
                    if($product_quantity){
                        $product_quantity_html = "<td class='$product_quantity_mobile'>". mb_convert_encoding($qty, 'UTF-8', 'UTF-8')."</td>";
                    }
                    $price = $row->variants[0]->price;
                    if($shop == "base23-5522.myshopify.com"){
                        $price = $price." ".$currency;
                    }
                    else{
                        $price = $currency." ".$price;
                    }
                    if(count($row->variants) > 1){
                        $priceArray = [];
                        foreach($row->variants as $variant){
                            $priceArray[] =  $variant->price;
                        }
                        $min = min($priceArray);
                        $max = max($priceArray);
                        $price = 'From '.$min.' - '.$max;
                    }
                    $price = "<div class='fw-600' id='price-" . $row->id . "'>". $price. "</div>";
                    if($product_price){
                        $product_price_html = "<td class='$product_price_mobile'>". mb_convert_encoding($price, 'UTF-8', 'UTF-8')."</td>";
                    }
                    if($shop == "base23-5522.myshopify.com"){
                        $compare_price = $row->variants[0]->compare_at_price ? $row->variants[0]->compare_at_price." ".$currency : "-";
                    }
                    else{
                        $compare_price = $row->variants[0]->compare_at_price ? $currency." ".$row->variants[0]->compare_at_price : "-";
                    }
                    $html = "<div id='compare_price-" . $row->id . "'>" . $compare_price . "</div>";
                    if($compare_price){
                        $compare_price_html = "<td class='$compare_price_mobile'>". mb_convert_encoding($html, 'UTF-8', 'UTF-8')."</td>";
                    }

                    if($shop == "buyer-cellar-marketplace.myshopify.com"){
                        $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', '2023-01') . '/products/'.$row->variants[0]->product_id.'/metafields.json';
                        $metaFields = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint, [], 'GET');
                        $metaFields = json_decode($metaFields['response']);
                        if(isset($metaFields->metafields)){
                            $metaFields = $metaFields->metafields;
                            if(isset($metaFields) && !empty($metaFields)){
                                foreach($metaFields as $metafield){
                                    if($metafield->key == 'price_per_bottle'){
                                        if(!empty($metafield->value)){
                                            if(is_string($metafield->value)){

                                                $value = json_decode($metafield->value);
                                                if($value->currency_code == 'USD'){
                                                    $price_per_bottle = "$".$value->amount;
                                                } else {
                                                    $price_per_bottle = $value->amount." ".$value->currency_code;
                                                }
                                            } else {
                                                $price_per_bottle = $metafield->value;
                                            }
                                            $price_per_bottle_html = "<td class='$price_per_bottle_mobile'>". mb_convert_encoding($price_per_bottle, 'UTF-8', 'UTF-8')."</td>";
                                        }
                                        else{
                                            $price_per_bottle_html = "<td class='$price_per_bottle_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                        }
                                    }
                                    if($metafield->key == 'b_c_rank'){
                                        if(!empty($metafield->value)){

                                            $b_c_rank_html = "<td class='$b_c_rank_mobile'>". mb_convert_encoding($metafield->value, 'UTF-8', 'UTF-8')."</td>";
                                        }
                                        else{
                                            $b_c_rank_html = "<td class='$b_c_rank_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                        }
                                    }
                                    if($metafield->key == 'average_bottle_price'){
                                        if(!empty($metafield->value)){
                                            if(is_string($metafield->value)){

                                                $value = json_decode($metafield->value);
                                                if($value->currency_code == 'USD'){
                                                    $average_bottle_price = "$".$value->amount;
                                                } else {
                                                    $average_bottle_price = $value->amount." ".$value->currency_code;
                                                }
                                            } else {
                                                $average_bottle_price = $metafield->value;
                                            }
                                            $average_bottle_price_html = "<td class='$average_bottle_price_mobile'>". mb_convert_encoding($average_bottle_price, 'UTF-8', 'UTF-8')."</td>";
                                        }
                                        else{
                                            $average_bottle_price_html = "<td class='$average_bottle_price_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                        }
                                    }

                                }
                            }
                            else{

                                if($price_per_bottle){
                                    $price_per_bottle_html = "<td class='$price_per_bottle_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                }
                                if($b_c_rank){
                                    $b_c_rank_html = "<td class='$b_c_rank_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                }
                                if($average_bottle_price){
                                    $average_bottle_price_html = "<td class='$average_bottle_price_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                }

                            }
                        }
                    }

                    $productId = $row->id;
                    if(isset($row->image->src)){
                        $src = $row->image->src;
                    }
                    else{
                        $src = env('APP_URL')."public/images/white-image.png";
                    }
                    if(is_string($store->settings)){
                        $settings = json_decode($store->settings);
                    }
                    else{
                        $settings = $store->settings;
                    }
                    if(count($row->variants) > 1 ){
                        $cartAction = "<div class='text-center' id='cart-" . $row->id . "'><input style='margin: 0 auto;float: none; max-width: 70px' id='qty-" . $row->id . "' class='form-control productquantity mb-2' type='number' value='1' min='1' disabled='true' >";

                        $cartAction .="<p><select  data-productid='".$row->id."' class='form-control adpts_variation_select' id='variations-select-".$row->id."'><option  data-image='".$src."' data-stock='-' data-sku='-' data-inventory_management='-' data-inventory_policy='-'  data-price='".$row->variants[0]->price."' value='".$row->id."'>Select Option</option>";
                        foreach($row->variants as $variant){
                            $sku = $variant->sku ? $variant->sku : "-";
                            $image = "";
                            if(isset($variant->image_id)){
                                foreach($row->images as $rowimage){
                                    if($variant->image_id == $rowimage->id){
                                        $image = $rowimage->src;
                                        continue;
                                    }
                                }
                            }
                            else{
                            $image = env('APP_URL').'public/images/white-image.png';
                            }
                            $cartAction .= "<option data-image='".$image."' data-stock='".$variant->inventory_quantity."' data-sku='".$sku."' data-inventory_management='".$variant->inventory_management."' data-inventory_policy='".$variant->inventory_policy."'  data-price='".$variant->price."' value='".$variant->id."'>".$variant->title."</option>";
                        }
                        $cartAction .= "</select></p>";

                        if(isset($settings->styling_enable)){
                            $cartAction .= "<button type='button'  style='background-color: ".$settings->table_buttons." !important' data-variant='' data-productid='".$row->id ."' class='btn btn-info adpts-add-to-cart-btn ".$row->id ."' disabled='true' >Add To Cart</button></div>";
                        }
                        else{
                            $cartAction .= "<button type='button' data-variant='' data-productid='".$row->id ."' class='btn btn-info adpts-add-to-cart-btn ".$row->id ."' disabled='true' >Add To Cart</button></div>";
                        }
                    }
                    else{
                        if ("shopify" == $row->variants[0]->inventory_management) {
                            if($row->variants[0]->inventory_quantity <= 0 && "deny" == $row->variants[0]->inventory_policy){
                                $disabled = 'disabled="true"';
                            }
                            else{
                                $disabled = '';
                            }
                        }
                        else{
                            $disabled = '';
                        }
                            $productId = $row->id;
                        $cartAction = "<div class='text-center' id='cart-" . $productId . "'><input style='margin: 0 auto;float: none; max-width: 70px' id='qty-" . $productId . "' class='form-control productquantity' type='number' value='1' min='1' ".$disabled."  ><br>";


                        if(isset($settings->styling_enable)){
                            $cartAction .= "<button type='button'  style='background-color: ".$settings->table_buttons." !important' data-productid='".$productId ."' class='btn btn-info adpts-add-to-cart-btn ".$productId ."' ".$disabled ." >Add To Cart</button>";
                        }
                        else{
                            $cartAction .= "<button type='button' data-productid='".$row->id ."' class='btn btn-info adpts-add-to-cart-btn ".$row->id ."' ".$disabled ." >Add To Cart</button>";
                        }

                    }
                    if($cart_actions){
                        $cart_actions_html =  "<td class='$cart_actions_mobile' >". mb_convert_encoding($cartAction, 'UTF-8', 'UTF-8')."</td>";
                    }

                    foreach($settings->active_column as $column){
                        $datatable .=  ${$column. "_html"};

                    }
                    $datatable .= "</tr>";
                }

            }
            return ["products"=>$datatable,"pageInfo"=>json_encode($pI)];
        }
        else{
            return response(['Store not Found']);
        }
    }

    public function getProductsAjaxBase23(Request $request)
    {


        $shop = $request->shop;
        if (!str_contains($shop, '.myshopify.com')) {
           $shop = AppController::getShopifyDomain($shop);
        }
        $store = Store::where('shop_url', $shop)->first();

        // if($store->plan_id == 2 && empty($productid)){
        //     $page_url = $request->page_url;

        //     $specificTable = SpecificTable::where('url', $page_url)->where('shop', $shop)->orderBy('id','DESC')->first();
        //     if($specificTable){
                $get_collection = $request->base23_collections;
        //     }
        //     else
        //     {
        //         $get_collection = "";
        //     }
        // }
        // else
        // {
        //     $get_collection = "";
        // }
        $pageInfo = $request->pageInfo;

        if( !empty( $store->shopify_token ) ){
            if(is_string($store->settings)){
                $settings = json_decode($store->settings);
            }
            else{
                $settings = $store->settings;
            }

            if(empty($get_collection)){
                $html='<tr><td colspan="'.(count($settings->active_column)-1).'"></td></tr>';
                return ["products"=>$html,"pageInfo"=>NULL];
            }
            if(isset($settings->table_length)){
                $tableLength = $settings->table_length;
            }
            else{
                $tableLength = 15;
            }
            if(isset($settings->desc_length)){
                $descLength = $settings->desc_length;
            }
            else{
                $descLength = 100;
            }
            $excludeProducts = [];
            if(isset($settings->exclude_collections)){
                foreach($settings->exclude_collections as $collection_id){
                    $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$collection_id.'/products.json';
                    $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                    $products = json_decode($products['response']);
                    $products = $products->products;
                    foreach($products as $product){
                        $excludeProducts[] = $product->id;

                    }
                }
            }

            if(isset($request->collection_id) || (isset($get_collection) && !empty($get_collection)) || isset($request->min_price) || isset($request->max_price)){

                $collection_id = isset($request->collection_id) ? $request->collection_id : "" ;
                $min_price = isset($request->min_price) ? $request->min_price : "" ;
                $max_price = isset($request->max_price) ? $request->max_price : "" ;


                if($collection_id != '' || $get_collection != ''){

                    $pI = [];
                    $pI['prev'] = NULL;
                    $pI['next'] = NULL;
                    if($collection_id != '' ){
                        $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$collection_id.'/products.json';
                        $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                        $products = json_decode($products['response']);

                        $products = $products->products;
                    }
                    elseif($get_collection != '' )
                    {
                        $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$get_collection.'/products.json';

                        $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                        $products = json_decode($products['response']);

                        $products = $products->products;
                        // if($min_price != '' || $max_price != ''){
                        //     $products = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array('limit'=>250) ,'GET');
                        //     $pageInfo = NULL;
                        //     $pI = [];
                        //     $pI['prev'] = NULL;
                        //     $pI['next'] = NULL;
                        // }
                    }

                }
                else{
                    $store = Store::where('shop_url', $shop)->first();
                    $storeModel = new Store();
                    if(is_string($store->settings)){
                        $settings = json_decode($store->settings);
                    }
                    else{
                        $settings = $store->settings;
                    }
                    $query = null;
                    if($request->next == "true"){
                        $query = 'next';
                    }
                    if($request->next == "false"){
                        $query = 'prev';
                    }
                    if($min_price != '' || $max_price != ''){

                        $products = $storeModel->get_all_shopify_products($request->shop,$store->shopify_token,['limit'=>250]);
                        $pageInfo = NULL;
                        $pI = [];
                        $pI['prev'] = NULL;
                        $pI['next'] = NULL;
                    }
                    else{
                        if(isset($settings->table_length)){
                            $tableLength = $settings->table_length;
                        }
                        else{
                            $tableLength = 15;
                        }

                        $productsArray = $storeModel->get_shopify_products($request,$request->shop,$store->shopify_token,['limit'=>250],$query,$pageInfo);
                        $products = $productsArray['products'];
                        $pageInfo = $productsArray['pageInfo'];
                        $pI = [];
                        if($pageInfo !== NULL){
                            if(isset($pageInfo->next) && $pageInfo->next !== NULL){
                                $pI['next'] = $pageInfo->next;
                            }else{
                                $pI['next'] = NULL;
                            }
                            if(isset($pageInfo->prev) && $pageInfo->prev !== NULL){
                                $pI['prev'] = $pageInfo->prev;
                            }else{
                                $pI['prev'] = NULL;
                            }
                        }
                    }

                }
                $allProducts = array();
                for ($i=0; $i < count($products); $i++) {

                    $allProducts[] = $products[$i];
                }

                $storeModel = new Store();
                $products = $storeModel->get_all_shopify_products($request->shop,$store->shopify_token,['limit'=>250]);

                $mainProducts = [];

                foreach($products as $product){
                    $add = true;

                    if(count($product->variants) > 1){
                        $priceArray = [];
                        foreach($product->variants as $variant){
                            $priceArray[] = $variant->price;

                        }
                        $var_min_price = min( $priceArray )  ? min( $priceArray ) : 0;

                        $var_max_price = max( $priceArray )  ? max( $priceArray ) : 0;

                        if ($min_price != "" && $max_price == "") {
                                if ($var_min_price < $min_price) {
                                    $add = false;
                                    continue;
                                }
                        } else if ($min_price == "" && $max_price != "") {
                            if ($var_max_price > $max_price) {
                                $add = false;
                                continue;
                            }
                        } else if ($min_price != "" && $max_price != "") {
                            if ($var_min_price >= $min_price && $var_max_price <= $max_price ) {
                                $add = true;
                                continue;
                            } else{
                                $add = false;
                                continue;
                            }
                        }
                    }
                    else{

                        $price_check = $product->variants[0]->price;

                        if ($min_price != "" && $max_price == "") {
                            if ($price_check < $min_price) {
                                $add = false;
                            }
                        } else if ($min_price == "" && $max_price != "") {
                            if ($price_check > $max_price) {
                                $add = false;
                            }
                        } else if ($min_price != "" && $max_price != "") {
                            if ($price_check < $min_price) {
                                $add = false;
                            } else if ($price_check > $max_price) {
                                $add = false;
                            }
                        }
                    }

                    foreach($allProducts as $aP){
                        if($aP->id == $product->id && $add == true){
                            $mainProducts[] = $product;
                        }
                    }
                }
                $products = $mainProducts;

            }
            else{


                $store = Store::where('shop_url', $request->shop)->first();
                $storeModel = new Store();
                if(is_string($store->settings)){
                    $settings = json_decode($store->settings);
                }
                else{
                    $settings = $store->settings;
                }
                $query = null;
                if($request->next == "true"){
                    $query = 'next';
                }
                if($request->next == "false"){
                    $query = 'prev';
                }
                //return $query;
                if(isset($settings->table_type) && $settings->table_type == 2){

                    $products = $storeModel->get_all_shopify_products($request->shop,$store->shopify_token,['limit'=>250]);

                    $pI = [];
                    $pI['prev'] = NULL;
                    $pI['next'] = NULL;
                }
                else{

                    if(isset($settings->table_length)){
                        $tableLength = $settings->table_length;
                    }
                    else{
                        $tableLength = 15;
                    }
                    $productsArray = $storeModel->get_shopify_products($request,$request->shop,$store->shopify_token,['limit'=>$tableLength],$query,$pageInfo);

                    $products = $productsArray['products'];
                    $pageInfo = $productsArray['pageInfo'];

                    $pI = [];
                    if($pageInfo !== NULL){
                        if($pageInfo->hasNextPage()){
                            $pI['next'] = $pageInfo->getNextPageQuery();
                        }else{
                            $pI['next'] = NULL;
                        }
                        if($pageInfo->hasPreviousPage()){
                            $pI['prev'] = $pageInfo->getPreviousPageQuery();
                        }else{
                            $pI['prev'] = NULL;
                        }
                    }
                }

                $mainProducts = [];
                if(count($excludeProducts) > 0){
                    foreach($products as $product){
                        if(!in_array($product->id,$excludeProducts)){
                            $mainProducts[] = $product;
                        }
                    }
                    $products = $mainProducts;
                }

                // if(count($mainProducts) != $tableLength && $settings->table_type == 1){

                //     $length = $tableLength-count($mainProducts);
                //     if(isset($settings->table_length)){
                //         $tableLength = $settings->table_length+$length;
                //     }
                //     else{
                //         $tableLength = 15+$length;
                //     }

                //     $query = null;
                //     if($request->next == "true"){
                //         $query = 'next';
                //     }
                //     if($request->next == "false"){
                //         $query = 'prev';
                //     }
                //     $productsArray = $storeModel->get_shopify_products($request,$request->shop,$store->shopify_token,['limit'=>$tableLength],$query,$pageInfo);

                //     $products = $productsArray['products'];
                //     $pageInfo = $productsArray['pageInfo'];

                //     $pI = [];
                //     if($pageInfo !== NULL){
                //         if($pageInfo->hasNextPage()){
                //             $pI['next'] = $pageInfo->getNextPageQuery();
                //         }else{
                //             $pI['next'] = NULL;
                //         }
                //         if($pageInfo->hasPreviousPage()){
                //             $pI['prev'] = $pageInfo->getPreviousPageQuery();
                //         }else{
                //             $pI['prev'] = NULL;
                //         }
                //     }

                //     $mainProducts = [];

                //     if(count($excludeProducts) > 0){
                //         foreach($products as $product){
                //             if(!in_array($product->id,$excludeProducts)){
                //                 $mainProducts[] = $product;
                //             }
                //         }
                //         $products = $mainProducts;
                //     }

                // }
            }
            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/shop.json';
            $shopifyShop = ShopController::shopify_rest_call($store->shopify_token, $request->shop, $api_endpoint , array() ,'GET');
            $shopifyShop = json_decode($shopifyShop['response']);
            $shopifyShop = $shopifyShop->shop;
            $currency = $shopifyShop->currency;
            $datatable = '';

            $checkbox = in_array("checkbox", $settings->active_column) ? true : false;
            $product_image = in_array("product_image", $settings->active_column) ? true : false;
            $product_name = in_array("product_name", $settings->active_column) ? true : false;
            $product_description = in_array("product_description", $settings->active_column) ? true : false;
            $product_sku = in_array("product_sku", $settings->active_column) ? true : false;
            $product_quantity = in_array("product_quantity", $settings->active_column) ? true : false;
            $product_price = in_array("product_price", $settings->active_column) ? true : false;
            $compare_price = in_array("compare_price", $settings->active_column) ? true : false;
            $cart_actions = in_array("cart_actions", $settings->active_column) ? true : false;
            $starttid = in_array("starttid", $settings->active_column) ? true : false;
            $sluttid = in_array("sluttid", $settings->active_column) ? true : false;
            $startdatum = in_array("startdatum", $settings->active_column) ? true : false;
            $slutdatum = in_array("slutdatum", $settings->active_column) ? true : false;
            $antal_ggr = in_array("antal_ggr", $settings->active_column) ? true : false;
            $kursnamn = in_array("kursnamn", $settings->active_column) ? true : false;
            $teacher_name = in_array("teacher_name", $settings->active_column) ? true : false;

            $checkbox_mobile = in_array("checkbox", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_image_mobile = in_array("product_image", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_name_mobile = in_array("product_name", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_description_mobile = in_array("product_description", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_sku_mobile = in_array("product_sku", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_quantity_mobile = in_array("product_quantity", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $product_price_mobile = in_array("product_price", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $compare_price_mobile = in_array("compare_price", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $cart_actions_mobile = in_array("cart_actions", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $starttid_mobile = in_array("starttid", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $sluttid_mobile = in_array("sluttid", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $startdatum_mobile = in_array("startdatum", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $slutdatum_mobile = in_array("slutdatum", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $antal_ggr_mobile = in_array("antal_ggr", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $kursnamn_mobile = in_array("kursnamn", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            $teacher_name_mobile = in_array("teacher_name", $settings->active_column_mobile) ? '' : 'hide_on_mobile';
            foreach($products as $row){

                if ((isset($settings->product_tag_enable) && $settings->product_tag_enable == 1)   && ($shop == "product-table-33.myshopify.com" || $shop == "merrillsoft.myshopify.com" )) {
                    $productTagAvailable = false;
                    if (isset($request->customer) && $row->tags != "") {

                        $customer = explode(",",$request->customer);
                        foreach($customer as $customer){
                            $productTagAvailable = str_contains($row->tags, $customer) ? true : false;
                        }
                    }
                }
                else{
                    $productTagAvailable = true;
                }

                if($productTagAvailable){
                    $datatable .= "<tr>";
                    if(gettype($row) == "array"){
                        $row = (object) $row;
                    }

                    if($shop == "base23-5522.myshopify.com"){
                        $api_endpoint = '/admin/api/' . env('SHOPIFY_API_VERSION', '2023-01') . '/products/'.$row->variants[0]->product_id.'/metafields.json';
                        $metaFields = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint, [], 'GET');
                        $metaFields = json_decode($metaFields['response']);
                        $metaFields = $metaFields->metafields;
                        if(isset($metaFields) && !empty($metaFields)){
                            foreach($metaFields as $metafield){
                                if($metafield->key == 'starttid'){
                                    if(!empty($metafield->value)){
                                        $dateTime = $metafield->value;
                                        $starttid = Carbon::parse($dateTime)->format('H:i');
                                        $starttid_html = "<td class='$starttid_mobile'>". mb_convert_encoding($starttid, 'UTF-8', 'UTF-8')."</td>";
                                    }
                                    else{
                                        $starttid_html = "<td class='$starttid_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                    }
                                }
                                if($metafield->key == 'sluttid'){
                                    if(!empty($metafield->value)){

                                        $dateTime = $metafield->value;
                                        $sluttid = Carbon::parse($dateTime)->format('H:i');
                                        $sluttid_html = "<td class='$sluttid_mobile'>". mb_convert_encoding($sluttid, 'UTF-8', 'UTF-8')."</td>";
                                    }
                                    else{
                                        $sluttid_html = "<td class='$sluttid_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                    }
                                }
                                if($metafield->key == 'startdatum'){
                                    if(!empty($metafield->value)){
                                        $dateTime = $metafield->value;
                                        $startdatum = Carbon::parse($dateTime)->format('Y-m-d');
                                        $startdatum_html = "<td class='$startdatum_mobile'>". mb_convert_encoding($startdatum, 'UTF-8', 'UTF-8')."</td>";
                                    }
                                    else{
                                        $startdatum_html = "<td class='$startdatum_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                    }
                                }
                                if($metafield->key == 'slutdatum'){
                                    if(!empty($metafield->value)){
                                        $dateTime = $metafield->value;
                                        $slutdatum = Carbon::parse($dateTime)->format('Y-m-d');
                                        $slutdatum_html = "<td class='$slutdatum_mobile'>". mb_convert_encoding($slutdatum, 'UTF-8', 'UTF-8')."</td>";
                                    }
                                    else{
                                        $slutdatum_html = "<td class='$slutdatum_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                    }
                                }
                                if($metafield->key == 'antal_ggr'){
                                    if(!empty($metafield->value)){
                                        $antal_ggr_html = "<td class='$antal_ggr_mobile'>". mb_convert_encoding($metafield->value, 'UTF-8', 'UTF-8')."</td>";
                                    }
                                    else{
                                        $antal_ggr_html = "<td class='$antal_ggr_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                    }
                                }
                                if($metafield->key == 'kursnamn'){
                                    if(!empty($metafield->value)){
                                        $kursnamn_html = "<td class='$kursnamn_mobile'>". mb_convert_encoding($metafield->value, 'UTF-8', 'UTF-8')."</td>";
                                    }
                                    else{
                                        $kursnamn_html = "<td class='$kursnamn_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                    }
                                }
                                if($metafield->key == 'teacher_name'){
                                    if(!empty($metafield->value)){
                                        $teacher_name_html = "<td class='$teacher_name_mobile'>". mb_convert_encoding($metafield->value, 'UTF-8', 'UTF-8')."</td>";
                                    }
                                    else{
                                        $teacher_name_html = "<td class='$teacher_name_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                                    }
                                }
                            }
                        }
                        else{

                            if($starttid){
                                $starttid_html = "<td class='$starttid_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                            }
                            if($sluttid){
                                $sluttid_html = "<td class='$sluttid_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                            }
                            if($startdatum){
                                $startdatum_html = "<td class='$startdatum_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                            }
                            if($slutdatum){
                                $slutdatum_html = "<td class='$slutdatum_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                            }
                            if($antal_ggr){
                                $antal_ggr_html = "<td class='$antal_ggr_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                            }
                            if($kursnamn){
                                $kursnamn_html = "<td class='$kursnamn_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                            }
                            if($teacher_name){
                                $teacher_name_html = "<td class='$teacher_name_mobile'>". mb_convert_encoding('', 'UTF-8', 'UTF-8')."</td>";
                            }
                        }
                    }
                    if ($row->variants[0]->inventory_quantity == 0 && "deny" == $row->variants[0]->inventory_policy) {
                        $html = "<td class='$checkbox_mobile' id='cart-checkbox-" . $row->id . "'><input type='checkbox' data-id='" . $row->variants[0]->id . "' class='checkbox adpts_checkbox' id='checkbox-" . $row->id . "' disabled='true' ></td>";
                    }
                    elseif(count($row->variants) > 1 ){
                        $html = "<td class='$checkbox_mobile' id='cart-checkbox-" . $row->id . "'><input type='checkbox' data-id='" . $row->variants[0]->id . "' class='checkbox adpts_checkbox' id='checkbox-" . $row->id . "' disabled='true' ></td>";
                    }
                    else{

                        $html = "<td class='$checkbox_mobile' id='cart-checkbox-" . $row->id . "'><input type='checkbox' data-id='" . $row->variants[0]->id . "' class='checkbox adpts_checkbox' id='checkbox-" . $row->id . "'  ></td>";
                    }
                    if($checkbox){
                        $checkbox_html = "". mb_convert_encoding($html, 'UTF-8', 'UTF-8')."";
                    }

                    if ($row->image == null) {
                        $image = env('APP_URL'). 'public/images/white-image.png';
                    } else {
                        $image = $row->image->src;
                    }

                    if(is_string($store->settings)){
                        $settings = json_decode($store->settings);
                    }
                    else{
                        $settings = $store->settings;
                    }
                    if(isset($settings->image_styling_enable)){
                        $width = $settings->width;
                        $height = $settings->height;
                    }
                    else{
                        $width = 100;
                        $height = 100;
                    }
                    $image_html =  "<div class='text-center' id='image-" . $row->id . "'><img class='pic' width='" . $width . "' height='" . $height . "' src='" . $image . "' alt=''></div>";
                    if($product_image){
                        $product_image_html = "<td class='$product_image_mobile' >". mb_convert_encoding($image_html, 'UTF-8', 'UTF-8')."</td>";
                    }
                    if($product_name){
                        if($shop == 'buyer-cellar-marketplace.myshopify.com'){
                            $product_name_html = "<td class='$product_name_mobile'><a href='https://".$shop."/products/".$row->handle."'>". mb_convert_encoding($row->title, 'UTF-8', 'UTF-8')."</a></td>";

                        }else{

                            $product_name_html = "<td class='$product_name_mobile'>". mb_convert_encoding($row->title, 'UTF-8', 'UTF-8')."</td>";
                        }
                    }
                    $string = "<div>" . $row->body_html . "</div>";
                    $body_html = strip_tags($string);
                    if (strlen($body_html) > $descLength) {
                        $body_html = substr($body_html, 0, $descLength);
                        $fullbody = $string;
                        $shortbody_html = true;
                    } else {
                        $shortbody_html = false;
                        $fullbody = $body_html;
                        $body_html = $body_html;
                    }
                    $tableData = '';
                    if ($shortbody_html) {
                        $tableData .= "    <p class='m-0 text-muted'>" . $body_html . "...<a class='btn-link' data-bs-toggle='modal' data-bs-target='#exampleModal_" . $row->id . "' style='cursor: pointer !important'>Read more</a> </p>";
                        $tableData .= "        <div class='modal fade' id='exampleModal_" . $row->id . "' tabindex='-1' aria-labelledby='exampleModal_" . $row->id . "Label' aria-hidden='true'>";
                        $tableData .= "            <div class='modal-dialog modal-dialog-centered'>";
                        $tableData .= "                <div class='modal-content'>";
                        $tableData .= "                    <div class='modal-header'>";
                        $tableData .= "                        <h5 class='modal-title' id='exampleModal_" . $row->id . "Label'>Description</h5>";
                        $tableData .= "                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
                        $tableData .= "                    </div>";
                        $tableData .= "                    <div class='modal-body'>" . $fullbody . "</div>";
                        $tableData .= "                    <div class='modal-footer'>";
                        $tableData .= "                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>";
                        $tableData .= "                    </div>";
                        $tableData .= "                </div>";
                        $tableData .= "            </div>";
                        $tableData .= "        </div>";
                        // $tableData .= "    </td>";
                    } else {
                        $tableData .= "    <p class='m-0 text-muted'>" . $body_html . "</p>";
                    }
                    if($product_description){
                        $product_description_html = "<td class='$product_description_mobile' >". mb_convert_encoding($tableData, 'UTF-8', 'UTF-8')."</td>";
                    }
                    $sku = $row->variants[0]->sku ? $row->variants[0]->sku : "-";
                    if($sku != "-"){
                        $html = "<div id='sku-" . $row->id . "'><a href='https://".$request->shop."/products/".$row->handle."'>" . $sku . "</a></div>";
                    }
                    else{
                        $html = "<div id='sku-" . $row->id . "'>" . $sku . "</div>";
                    }
                    if($product_sku){
                        $product_sku_html = "<td class='$product_sku_mobile' >". mb_convert_encoding($html, 'UTF-8', 'UTF-8')."</td>";
                    }

                    $qty = "-";
                    if (count($row->variants) < 2) {
                        $row->id = $row->variants[0]->id;
                        $qty = "in stock";
                        if ("shopify" == $row->variants[0]->inventory_management) {
                            $qty = $row->variants[0]->inventory_quantity;
                            if ("deny" == $row->variants[0]->inventory_policy) {
                                $qty = $qty . " in stock";
                            } else if ("continue" == $row->variants[0]->inventory_policy) {
                                $qty = $qty . " in stock<br><p>Available on backorder</p>";
                            }
                        }
                    }
                    if($product_quantity){
                        $product_quantity_html = "<td class='$product_quantity_mobile' >". mb_convert_encoding($qty, 'UTF-8', 'UTF-8')."</td>";
                    }
                    $price = $row->variants[0]->price;
                    if($shop == "base23-5522.myshopify.com"){
                        $price = $price." ".$currency;
                    }
                    else{
                        $price = $currency." ".$price;
                    }
                    if(count($row->variants) > 1){
                        $priceArray = [];
                        foreach($row->variants as $variant){
                            $priceArray[] =  $variant->price;
                        }
                        $min = min($priceArray);
                        $max = max($priceArray);
                        $price = 'From '.$min.' - '.$max;
                    }
                    $price = "<div class='fw-600' id='price-" . $row->id . "'>". $price. "</div>";
                    if($product_price){
                        $product_price_html = "<td class='$product_price_mobile' >". mb_convert_encoding($price, 'UTF-8', 'UTF-8')."</td>";
                    }
                    if($shop == "base23-5522.myshopify.com"){
                        $compare_price = $row->variants[0]->compare_at_price ? $row->variants[0]->compare_at_price." ".$currency : "-";
                    }
                    else{
                        $compare_price = $row->variants[0]->compare_at_price ? $currency." ".$row->variants[0]->compare_at_price : "-";
                    }
                    $html = "<div id='compare_price-" . $row->id . "'>" . $compare_price . "</div>";
                    if($compare_price){
                        $compare_price_html = "<td class='$compare_price_mobile' >". mb_convert_encoding($html, 'UTF-8', 'UTF-8')."</td>";
                    }

                    $productId = $row->id;
                    if(isset($row->image->src)){
                        $src = $row->image->src;
                    }
                    else{
                        $src = env('APP_URL')."public/images/white-image.png";
                    }
                    if(is_string($store->settings)){
                        $settings = json_decode($store->settings);
                    }
                    else{
                        $settings = $store->settings;
                    }
                    if(count($row->variants) > 1 ){
                        $cartAction = "<div class='text-center' id='cart-" . $row->id . "'><input style='margin: 0 auto;float: none; max-width: 70px' id='qty-" . $row->id . "' class='form-control productquantity mb-2' type='number' value='1' min='1' disabled='true' >";

                        $cartAction .="<p><select  data-productid='".$row->id."' class='form-control adpts_variation_select' id='variations-select-".$row->id."'><option  data-image='".$src."' data-stock='-' data-sku='-' data-inventory_management='-' data-inventory_policy='-'  data-price='".$row->variants[0]->price."' value='".$row->id."'>Select Option</option>";
                        foreach($row->variants as $variant){
                            $sku = $variant->sku ? $variant->sku : "-";
                            $image = "";
                            if(isset($variant->image_id)){
                                foreach($row->images as $rowimage){
                                    if($variant->image_id == $rowimage->id){
                                        $image = $rowimage->src;
                                        continue;
                                    }
                                }
                            }
                            else{
                            $image = env('APP_URL').'public/images/white-image.png';
                            }
                            $cartAction .= "<option data-image='".$image."' data-stock='".$variant->inventory_quantity."' data-sku='".$sku."' data-inventory_management='".$variant->inventory_management."' data-inventory_policy='".$variant->inventory_policy."'  data-price='".$variant->price."' value='".$variant->id."'>".$variant->title."</option>";
                        }
                        $cartAction .= "</select></p>";

                        if(isset($settings->styling_enable)){
                            $cartAction .= "<button type='button'  style='background-color: ".$settings->table_buttons." !important' data-variant='' data-productid='".$row->id ."' class='btn btn-info adpts-add-to-cart-btn ".$row->id ."' disabled='true' >Add To Cart</button></div>";
                        }
                        else{
                            $cartAction .= "<button type='button' data-variant='' data-productid='".$row->id ."' class='btn btn-info adpts-add-to-cart-btn ".$row->id ."' disabled='true' >Add To Cart</button></div>";
                        }
                    }
                    else{
                        if ("shopify" == $row->variants[0]->inventory_management) {
                            if($row->variants[0]->inventory_quantity <= 0 && "deny" == $row->variants[0]->inventory_policy){
                                $disabled = 'disabled="true"';
                            }
                            else{
                                $disabled = '';
                            }
                        }
                        else{
                            $disabled = '';
                        }
                            $productId = $row->id;
                        $cartAction = "<div class='text-center input-group' id='cart-" . $productId . "'><input style='margin: 0 auto;float: none; max-width: 70px' id='qty-" . $productId . "' class='form-control productquantity' type='number' value='1' min='1' ".$disabled."  >";


                        if(isset($settings->styling_enable)){
                            $cartAction .= "<div class='input-group-append'> <button type='button'  style='background-color: ".$settings->table_buttons." !important' data-productid='".$productId ."' class='btn btn-info adpts-add-to-cart-btn ".$productId ."' ".$disabled ." >Add To Cart</button></div>";
                        }
                        else{
                            $cartAction .= "<button type='button' data-productid='".$row->id ."' class='btn btn-info adpts-add-to-cart-btn ".$row->id ."' ".$disabled ." >Add To Cart</button>";
                        }

                    }
                    if($cart_actions){
                        $cart_actions_html = "<td class='$cart_actions_mobile' >". mb_convert_encoding($cartAction, 'UTF-8', 'UTF-8')."</td>";
                    }

                    foreach($settings->active_column as $column){
                        $datatable .=  ${$column. "_html"};

                    }
                    $datatable .= "</tr>";
                }

            }
            return ["products"=>$datatable,"pageInfo"=>json_encode($pI)];
        }
        else{
            return response(['Store not Found']);
        }
    }
    public static function getProductTypes(Request $request ) {
        $shop = $request->shop;
        if (!str_contains($shop, '.myshopify.com')) {
           $shop = AppController::getShopifyDomain($shop);
        }
        $store = Store::where('shop_url', $shop)->first();
        $allCollections = array();


        if( !empty( $store->shopify_token ) ){
            if(is_string($store->settings)){
                $settings = json_decode($store->settings);
            }
            else{
                $settings = $store->settings;
            }
            if(isset($settings->exclude_collections) && !empty($settings->exclude_collections)){
                $exclude_collections= $settings->exclude_collections;
            }
            else{
                $exclude_collections = [];
            }

            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/smart_collections.json';
            $collections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
            $collections = json_decode($collections['response']);
            $collections = isset($collections->smart_collections) ? $collections->smart_collections : [];
            $smart_collections = array();
            if( !empty( $collections ) ){
                foreach($collections as $collection ){
                    if (!in_array($collection->id, $exclude_collections)){
                        $allCollections[$collection->id] = $collection->title;
                    }
                }
            }
            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/custom_collections.json';
            $collections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
            $collections = json_decode($collections['response']);
            $collections = isset($collections->custom_collections) ? $collections->custom_collections : [];
            $custom_collections = array();
            if( !empty( $collections ) ){
                foreach($collections as $collection ){
                    if (!in_array($collection->id, $exclude_collections)){
                        $allCollections[$collection->id] = $collection->title;
                    }
                }
            }
        }
        if(isset($request->page_url) && $request->page_url <> ''){

            $page_url = $request->page_url;
            $specificTable = SpecificTable::where('url', $page_url)->where('shop', $shop)->orderBy('id','DESC')->first();
            if($specificTable){
                $get_collection = $specificTable->collection_id;
            }
            else
            {
                $get_collection = "";
            }
            $newCollection = [];
            foreach ($allCollections as $key => $collection) {
                if($get_collection != ""){
                    if($key == $get_collection){
                        $newCollection[$key] = $collection;
                    }
                }
                else{
                    $newCollection[$key] = $collection;
                }
            }
            $allCollections = $newCollection;
        }
        // $allCollections = array($smart_collections, $custom_collections);
        return  $allCollections;
    }
    public static function getCollections($shop ) {
        $store = Store::where('shop_url', $shop)->first();

        $allCollections = array();

        if( !empty( $store->shopify_token ) ){

            if(isset($settings->exclude_collections) && !empty($settings->exclude_collections)){
                $exclude_collections= $settings->exclude_collections;
            }
            else{
                $exclude_collections = [];
            }
            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/smart_collections.json';
            $collections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
            $collections = json_decode($collections['response']);
            $collections = isset($collections->smart_collections) ? $collections->smart_collections : [];
            $smart_collections = array();
            if( !empty( $collections ) ){
                foreach($collections as $collection ){
                    if (!in_array($collection->id, $exclude_collections)){
                        $allCollections[$collection->id] = $collection->title;
                    }
                }
            }
            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/custom_collections.json';
            $collections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
            $collections = json_decode($collections['response']);
            $collections = isset($collections->custom_collections) ? $collections->custom_collections : [];
            $custom_collections = array();
            if( !empty( $collections ) ){
                foreach($collections as $collection ){
                    if (!in_array($collection->id, $exclude_collections)){
                        $allCollections[$collection->id] = $collection->title;
                    }
                }
            }
        }
        // $allCollections = array($smart_collections, $custom_collections);
        return  $allCollections;
    }

    public static function getCollectionsWithProducts(Request $req ) {
        $shop = $req->shop;
        $store = Store::where('shop_url', $shop)->first();

        $allCollections = array();

        if( !empty( $store->shopify_token ) ){
            if(is_string($store->settings)){
                $settings = json_decode($store->settings);
            }
            else{
                $settings = $store->settings;
            }
            if(isset($settings->exclude_collections) && !empty($settings->exclude_collections)){
                $exclude_collections= $settings->exclude_collections;
            }
            else{
                $exclude_collections = [];
            }
            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/smart_collections.json';
            $collections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
            $collections = json_decode($collections['response']);
            $collections = $collections->smart_collections;
            $smart_collections = array();
            if( !empty( $collections ) ){
                foreach($collections as $collection ){
                    if (in_array($collection->id, $exclude_collections)){
                        $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$collection->id.'/products.json';
                        $shopifyCollections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
                        $shopifyCollections = json_decode($shopifyCollections['response']);
                        $shopifyCollections = $shopifyCollections->products;
                        $allCollections[$collection->id] = $shopifyCollections;


                    }
                }
            }
            $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/custom_collections.json';
            $collections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
            $collections = json_decode($collections['response']);
            $collections = $collections->custom_collections;
            $custom_collections = array();
            if( !empty( $collections ) ){
                foreach($collections as $collection ){
                    if (in_array($collection->id, $exclude_collections)){
                        $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$collection->id.'/products.json';
                        $shopifyCollections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
                        $shopifyCollections = json_decode($shopifyCollections['response']);
                        $shopifyCollections = $shopifyCollections->products;
                        $allCollections[$collection->id] = $shopifyCollections;

                    }
                }
            }
        }
        return  $allCollections;
    }



    public static function getSingleCollection(Request $request) {
        $store = Store::where('shop_url', $request->shop)->first();
        $shop= $request->shop;
        if(is_string($store['settings'])){
            $settings = json_decode($store['settings']);
        }
        $exists = false;
        if(isset($settings)){
            if(isset($settings->exclude_collections) && !empty($settings->exclude_collections)){
                $collections= $settings->exclude_collections;

                foreach($collections as $key => $collection){
                    $api_endpoint = '/admin/api/'.env('SHOPIFY_API_VERSION','2023-01').'/collections/'.$collection.'/products.json';
                    $shopifyCollections = ShopController::shopify_rest_call($store->shopify_token, $shop, $api_endpoint , array() ,'GET');
                    $shopifyCollections = json_decode($shopifyCollections['response']);
                    $shopifyCollections = $shopifyCollections->products;

                    foreach($shopifyCollections as $key => $collection){
                        if($collection->id == $request->product_id){
                            $exists = true;
                            continue;
                        }

                    }
                }
            }
        }

        return  ["exists" => $exists];
    }
    public function specificTableSave(Request $request) {
        $data = $request->all();

        $request->validate([
            'collection' => 'required',
            'url' => 'required',
            'shop' => 'required',
        ]);
        $specificTable = new SpecificTable;
        $specificTable->collection_id = $data['collection'];
        $specificTable->title = $data['title'];
        $specificTable->url = rtrim($data['url'], "/");
        $specificTable->shop = $data['shop'];
        $specificTable->save();

        $previousUrl = app('url')->previous();
        return redirect()->to($previousUrl.'&'. http_build_query(['page'=>'SpecificTable']));
    }

    public function updateSpecificTable(Request $request){
        $data = $request->all();
        $specificTable = SpecificTable::find($request->specific_id);
        $specificTable->collection_id = $data['collection'];
        $specificTable->title = $data['title'];
        $specificTable->url = rtrim($data['url'], "/");
        $specificTable->shop = $data['shop'];
        $specificTable->update();
        $previousUrl = app('url')->previous();
        return redirect()->to($previousUrl.'&'. http_build_query(['page'=>'SpecificTable']));
    }
    public function deleteSpecificTable(Request $request){


        SpecificTable::find($request->id)->delete();
        return response()->json([
            'success' => 'Record deleted successfully!'
        ]);
    }

    public function getSpecificTable(Request $request){
        $specificTable = SpecificTable::find($request->id);
        return response()->json($specificTable);
    }
}
