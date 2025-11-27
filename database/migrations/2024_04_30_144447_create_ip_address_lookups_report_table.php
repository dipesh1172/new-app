<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIpAddressLookupsReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ip_address_lookups_report', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('ip_address_lookup_id')->nullable();
            $table->string('action')->nullable();
            $table->string('brand_id')->nullable();
            $table->string('vendor_id')->nullable();
            $table->string('office_id')->nullable();
            $table->string('brand_user_id')->nullable();

            $table->string('ip_address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('region_code')->nullable();
            $table->string('region_type')->nullable();
            $table->string('country_name')->nullable();
            $table->string('country_code')->nullable();
            $table->string('continent_name')->nullable();
            $table->string('continent_code')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('postal')->nullable();
            $table->string('calling_code')->nullable();
            $table->string('asn')->nullable();
            $table->string('asn_name')->nullable();
            $table->string('asn_domain')->nullable();
            $table->string('asn_route')->nullable();
            $table->string('asn_type')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_domain')->nullable();
            $table->string('company_network')->nullable();
            $table->string('company_type')->nullable();

            $table->boolean('is_tor')->default(0);
            $table->boolean('is_vpn')->default(0);
            $table->boolean('is_icloud_relay')->default(0);
            $table->boolean('is_proxy')->default(0);
            $table->boolean('is_datacenter')->default(0);
            $table->boolean('is_anonymous')->default(0);
            $table->boolean('is_known_attacker')->default(0);
            $table->boolean('is_known_abuser')->default(0);
            $table->boolean('is_threat')->default(0);
            $table->boolean('is_bogon')->default(0);
            $table->integer('vpn_score')->default(0);
            $table->integer('proxy_score')->default(0);
            $table->integer('threat_score')->default(0);
            $table->integer('trust_score')->default(0);

            $table->string('eztpv_confirmation_code')->nullable();
            $table->integer('eztpv_channel_id')->nullable();
            $table->integer('eztpv_market_id')->nullable();
            $table->string('eztpv_auth_fname')->nullable();
            $table->string('eztpv_auth_lname')->nullable();
            $table->string('eztpv_company_name')->nullable();
            $table->string('eztpv_auth_relationship')->nullable();
            $table->string('eztpv_btn')->nullable();
            $table->string('eztpv_email_address')->nullable();
            $table->string('eztpv_service_address1')->nullable();
            $table->string('eztpv_service_address2')->nullable();
            $table->string('eztpv_service_city')->nullable();
            $table->string('eztpv_service_state')->nullable();
            $table->string('eztpv_service_zip')->nullable();

            $table->index('created_at', 'idx_created_at');
            $table->index('updated_at', 'idx_updated_at');
            $table->index('deleted_at', 'idx_deleted_at');
            $table->index('action', 'idx_action');
            $table->index('brand_id', 'idx_brand_id');
            $table->index('vendor_id', 'idx_vendor_id');
            $table->index('office_id', 'idx_office_id');
            $table->index('brand_user_id', 'idx_brand_user_id');
            $table->index('ip_address', 'idx_ip_address');
            $table->index('eztpv_confirmation_code', 'idx_eztpv_confirmation_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ip_address_lookups_report');
    }
}
