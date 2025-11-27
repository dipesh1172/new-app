<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContractDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contract_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('client_id', 128);
            $table->string('brand_id', 128);
            $table->string('vendor_id', 128);
            $table->string('to_client', 128);
            $table->string('attention', 128);
            $table->string('address');
            $table->string('phone', 128);
            $table->string('email', 128);
            $table->string('federal_tax_id', 128);
            $table->string('cc_name', 128)->nullable();
            $table->string('cc_address')->nullable();
            $table->string('cc_phone', 128)->nullable();
            $table->string('cc_email', 128)->nullable();
            $table->string('client_coordinator_name', 128)->nullable();
            $table->string('client_coordinator_title', 128)->nullable();
            $table->string('client_coordinator_email', 128)->nullable();
            $table->string('client_coordinator_phone', 128)->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contract_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
