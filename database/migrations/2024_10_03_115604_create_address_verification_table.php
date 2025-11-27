<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class  CreateAddressVerificationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('address_verification', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36);
            $table->string('vendor_id', 36);
            $table->string('office_id', 36);
            $table->string('sales_rep_id', 36);
            $table->string('confirmation_code', 20)->nullable();
            $table->string('entered_address', 255)->nullable();
            $table->string('suggested_address', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('address_verification');
    }
}
