<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddCustomerCheck extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'customer_list_type',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('customer_list_type', 128)->nullable();
            }
        );

        DB::table('customer_list_type')->insert(
            array(
                array(
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'customer_list_type' => 'Blacklist'
                ),
                array(
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'customer_list_type' => 'Whitelist'
                )
            )
        );

        Schema::create(
            'customer_lists',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->integer('customer_list_type_id')->nullable();
                $table->string('brand_id', 36)->nullable();
                $table->string('utility_supported_fuel_id', 36)->nullable();
                $table->string('account_number1', 128)->nullable();
                $table->string('account_number2', 128)->nullable();
                $table->string('filename', 128)->nullable();
                $table->tinyInteger('processed', 2)->default(1);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul.
    }
}
