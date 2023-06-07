<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ShopifyStoresData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shopify_stores_data', function (Blueprint $table) {
            $table->id();
            $table->string('shop_url',100);
            $table->string('shopify_token',255);
            $table->bigInteger('current_charge_id')->nullable();
            $table->dateTime('trial_expiration_date')->nullable();
            $table->longText('settings')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shopify_stores_data');
    }
}
