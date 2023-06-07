<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::group(["middleware" => ["shopify-auth"]], function(){

// ShopController
    //install and unintstall
    Route::get('install','App\Http\Controllers\ShopController@generate_install_url')->name('install');
    Route::any('generate_token','App\Http\Controllers\ShopController@generate_and_save_token')->name('generate_token');
    Route::any('uninstall','App\Http\Controllers\ShopController@uninstall')->name('uninstall');
    //gdpr
    Route::any('gdpr_view_customer','App\Http\Controllers\ShopController@gdpr_view_customer');
    Route::any('gdpr_delete_customer','App\Http\Controllers\ShopController@gdpr_delete_customer');
    Route::any('gdpr_delete_shop','App\Http\Controllers\ShopController@gdpr_delete_shop');
    //app view and save setting
    Route::get('app_view','App\Http\Controllers\ShopController@app_view')->name('app_view');
    Route::get('/','App\Http\Controllers\ShopController@app_view')->name('app_view');
    Route::post('save_setting','App\Http\Controllers\ShopController@saveSetting')->name('saveSetting');
    Route::post('get_settings','App\Http\Controllers\ShopController@getSettings')->name('getSettings');
    Route::post('new_setting_save','App\Http\Controllers\ShopController@newSettingSave')->name('newSettingSave');

// ShopController

// BillingController
    Route::post('create_charge','App\Http\Controllers\BillingController@createCharge')->name('createCharge');
    Route::post('cancel_charge','App\Http\Controllers\BillingController@cancelCharge')->name('cancelCharge');
    Route::post('check_billing','App\Http\Controllers\BillingController@check_billing')->name('checkCurrentChargeStatus');
    Route::post('get_current_charge_status','App\Http\Controllers\BillingController@checkCurrentChargeStatus')->name('checkCurrentChargeStatus');
    Route::get('create_charge/{id}','App\Http\Controllers\BillingController@create_charge')->name('charge.create');
    Route::get('change_store_plan/{id}','App\Http\Controllers\BillingController@change_plan')->name('plan.change');
// BillingController

// AppController
    Route::post('get_products','App\Http\Controllers\AppController@getProducts')->name('getProducts');
    Route::post('get_collections','App\Http\Controllers\AppController@getCollections')->name('getCollections');
    Route::post('check_product_collection','App\Http\Controllers\AppController@getCollectionsWithProducts')->name('getCollectionsWithProducts');
    Route::get('get_products_ajax','App\Http\Controllers\AppController@getProductsAjax')->name('get_products_ajax');
    Route::get('get_products_ajax_base23','App\Http\Controllers\AppController@getProductsAjaxBase23')->name('get_products_ajax_base_23');
    Route::post('get_product_variants','App\Http\Controllers\AppController@getProductVariantsAjax')->name('get_product_variants');
    Route::post('get_product_types','App\Http\Controllers\AppController@getProductTypes')->name('getProductTypes');

    Route::post('get_specific_table','App\Http\Controllers\AppController@getSpecificTable')->name('getSpecificTable');
    Route::post('specific_table_save','App\Http\Controllers\AppController@specificTableSave')->name('specificTableSave');
    Route::post('update_specific_table','App\Http\Controllers\AppController@updateSpecificTable')->name('updateSpecificTable');
    Route::post('delete_specific_table','App\Http\Controllers\AppController@deleteSpecificTable')->name('updateSpecificTable');


    // variants app
    Route::post('get_variant_settings','App\Http\Controllers\ShopController@getVariantSettings')->name('getVariantSettings');
    Route::post('get_variants','App\Http\Controllers\VariantAppController@getVariants')->name('getVariants');
    Route::post('save_variant_table_settings','App\Http\Controllers\VariantAppController@saveVariantTableSettings')->name('saveVariantTableSettings');


// AppController

});
