<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class OptimizingTablesWithIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_alerts', function (Blueprint $table) {
            $table->index('event_id');
            $table->index('client_alert_id');
        });

        Schema::table('brand_client_alerts', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('client_alert_id');
        });

        Schema::table('brand_states', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('state_id');
        });

        Schema::table('brand_state_channels', function (Blueprint $table) {
            $table->index('brand_state_id');
            $table->index('channel_id');
        });

        Schema::table('brand_state_markets', function (Blueprint $table) {
            $table->index('brand_state_id');
            $table->index('market_id');
        });

        Schema::table('brand_state_languages', function (Blueprint $table) {
            $table->index('brand_state_id');
            $table->index('language_id');
        });

        Schema::table('brand_utilities', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('utility_id');
            $table->index('utility_external_id');
        });

        Schema::table('brand_utility_supported_fuels', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('utility_id');
            $table->index('utility_supported_fuel_id');
        });

        Schema::table('brand_hours', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('state_id');
        });

        Schema::table('brand_custom_fields', function (Blueprint $table) {
            $table->index('associated_with_id');
            $table->index('custom_field_id');
            $table->index('brand_id');
            $table->index('associated_with_type');
        });

        Schema::table('custom_field_storages', function (Blueprint $table) {
            $table->index('custom_field_id');
            $table->index('event_id');
            $table->index('product_id');
        });

        Schema::table('address_lookup', function (Blueprint $table) {
            $table->index('id_type');
            $table->index('type_id');
            $table->index('address_id');
        });

        Schema::table('event_product', function (Blueprint $table) {
            $table->index('event_id');
        });

        Schema::table('event_product_identifiers', function (Blueprint $table) {
            $table->index('event_product_id');
            $table->index('utility_account_type_id');
        });

        Schema::table('dispositions', function (Blueprint $table) {
            $table->index('brand_id');
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('script_id');
        });

        Schema::table('scripts', function (Blueprint $table) {
            $table->index('brand_id');
            $table->index('dnis_id');
        });

        Schema::table('uploads', function (Blueprint $table) {
            $table->index('client_id');
            $table->index('brand_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
