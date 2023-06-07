<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shopify_stores_data', function (Blueprint $table) {
            $table->integer('plan_id')->default(1)->nullable()->after('shopify_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shopify_stores_data', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });
    }
};
