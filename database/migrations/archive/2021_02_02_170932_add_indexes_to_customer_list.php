<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToCustomerList extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_lists', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('customer_list_type_id');
            $table->index('utility_supported_fuel_id');
            $table->index('account_number1');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Nope
    }
}
