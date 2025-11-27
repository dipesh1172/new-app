<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRateRestrictionToVendorRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vendor_rates', function (Blueprint $table) {
            $table->string('rate_id', 36)->nullable();
            $table->string('product_id', 36)->nullable()->change();
            $table->index(['vendors_id', 'product_id'], 'vendor_product_idx');
            $table->index(['vendors_id', 'rate_id'], 'vendor_rate_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vendor_rates', function (Blueprint $table) {
            $table->dropColumn(['rate_id']);
            $table->dropIndex('vendor_product_idx');
            $table->dropIndex('vendor_rate_idx');
        });
    }
}
