<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatsProductTermTypes extends Migration
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
                $table->double('product_monthly_fee')
                    ->after('product_service_fee')->nullable();
                $table->string('product_term_type', 32)
                    ->after('product_term')->nullable();
                $table->string('product_intro_term_type', 32)
                    ->after('product_intro_term')->nullable();
                $table->string('product_rate_type', 32)
                    ->after('product_name')->nullable();

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
                $table->dropColumn('product_monthly_fee');
                $table->dropColumn('product_term_type');
                $table->dropColumn('product_intro_term_type');
                $table->dropColumn('product_rate_type');
            }
        );
    }
}
