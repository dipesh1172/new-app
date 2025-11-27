<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDataEtlTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'stats_product',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('event_id', 36)->nullable();
                $table->timestamp('event_created_at')->nullable();
                $table->string('interaction_id', 36)->nullable();
                $table->timestamp('interaction_created_at')->nullable();
                $table->tinyInteger('eztpv_initiated')->default(0);
                $table->string('language', 16)->nullable();
                $table->string('channel', 16)->nullable();
                $table->string('confirmation_code', 24)->nullable();
                $table->string('result', 16)->nullable();
                $table->string('disposition_label', 32)->nullable();
                $table->string('disposition_reason', 255)->nullable();
                $table->string('source', 16)->nullable();
                $table->string('brand_id', 36)->nullable();
                $table->string('brand_name', 255)->nullable();
                $table->string('vendor_id', 36)->nullable();
                $table->string('vendor_name', 255)->nullable();
                $table->string('office_id', 36)->nullable();
                $table->string('office_label', 32)->nullable();
                $table->string('office_name', 255)->nullable();
                $table->string('market', 32)->nullable();
                $table->string('event_product_id', 36)->nullable();
                $table->string('commodity', 32)->nullable();
                $table->string('sales_agent_id', 36)->nullable();
                $table->string('sales_agent_name', 64)->nullable();
                $table->string('sales_agent_rep_id', 32)->nullable();
                $table->string('tpv_agent_id', 36)->nullable();
                $table->string('tpv_agent_name', 64)->nullable();
                $table->string('tpv_agent_label', 32)->nullable();
                $table->string('dnis', 32)->nullable();
                $table->string('structure_type', 32)->nullable();
                $table->string('company_name', 32)->nullable();
                $table->string('bill_first_name', 32)->nullable();
                $table->string('bill_middle_name', 32)->nullable();
                $table->string('bill_last_name', 32)->nullable();
                $table->string('auth_first_name', 32)->nullable();
                $table->string('auth_middle_name', 32)->nullable();
                $table->string('auth_last_name', 32)->nullable();
                $table->string('auth_relationship', 32)->nullable();
                $table->string('btn', 32)->nullable();
                $table->string('email_address', 255)->nullable();
                $table->string('billing_address1', 255)->nullable();
                $table->string('billing_city', 64)->nullable();
                $table->string('billing_state', 8)->nullable();
                $table->string('billing_zip', 8)->nullable();
                $table->string('billing_county', 32)->nullable();
                $table->string('billing_country', 32)->nullable();
                $table->string('service_address1', 255)->nullable();
                $table->string('service_city', 64)->nullable();
                $table->string('service_state', 8)->nullable();
                $table->string('service_zip', 8)->nullable();
                $table->string('service_county', 32)->nullable();
                $table->string('service_country', 32)->nullable();
                $table->string('rate_program_code', 32)->nullable();
                $table->string('rate_uom', 16)->nullable();
                $table->string('product_name', 64)->nullable();
                $table->string('external_rate_id', 64)->nullable();
                $table->integer('product_term')->nullable();
                $table->integer('product_intro_term')->nullable();
                $table->integer('product_daily_fee')->nullable();
                $table->integer('product_service_fee')->nullable();
                $table->integer('product_intro_service_fee')->nullable();
                $table->integer('product_rate_amount')->nullable();
                $table->integer('product_green_percentage')->nullable();
                $table->integer('product_cancellation_fee')->nullable();
                $table->integer('product_admin_fee')->nullable();
                $table->string('product_utility_name', 128)->nullable();
                $table->string('account_number1', 64)->nullable();
                $table->string('account_number2', 64)->nullable();
                $table->string('name_key', 8)->nullable();
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
        Schema::dropIfExists('stats_product');
    }
}
