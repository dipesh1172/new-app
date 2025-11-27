<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEnrollmentFileFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->string('product_utility_external_id', 128)
                    ->nullable()->after('product_utility_name');
                $table->string('rate_source_code', 128)
                    ->nullable()->after('rate_uom');
                $table->string('rate_promo_code', 128)
                    ->nullable()->after('rate_source_code');
                $table->string('rate_external_id', 128)
                    ->nullable()->after('rate_promo_code');
                $table->string('rate_renewal_plan', 128)
                    ->nullable()->after('rate_external_id');
                $table->string('rate_channel_source', 128)
                    ->nullable()->after('rate_renewal_plan');
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
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->dropColumn('product_utility_external_id');
                $table->dropColumn('rate_source_code');
                $table->dropColumn('rate_promo_code');
                $table->dropColumn('rate_external_id');
                $table->dropColumn('rate_renewal_plan');
                $table->dropColumn('rate_channel_source');
            }
        );
    }
}
