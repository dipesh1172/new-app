<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIpAddressLookupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ip_address_lookups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->softDeletes();

            $table->string('action')->nullable();
            $table->string('brand_id')->nullable();
            $table->string('vendor_id')->nullable();
            $table->string('office_id')->nullable();
            $table->string('brand_user_id')->nullable();

            $table->string('ip_address')->nullable();            
            $table->string('url')->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->string('status_code')->nullable();

            $table->index('created_at', 'idx_created_at');
            $table->index('updated_at', 'idx_updated_at');
            $table->index('deleted_at', 'idx_deleted_at');
            $table->index('action', 'idx_action');
            $table->index('brand_id', 'idx_brand_id');
            $table->index('vendor_id', 'idx_vendor_id');
            $table->index('office_id', 'idx_office_id');
            $table->index('brand_user_id', 'idx_brand_user_id');
            $table->index('ip_address', 'idx_ip_address');
            $table->index('status_code', 'idx_status_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ip_address_lookups');
    }
}
