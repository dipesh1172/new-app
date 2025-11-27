<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMaxAcctsToBrandConfig extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'brand_states', function (Blueprint $table) {
                $table->integer('max_accounts_res_tm')->default(0);
                $table->integer('max_accounts_res_dtd')->default(0);
                $table->integer('max_accounts_res_retail')->default(0);
                $table->integer('max_accounts_sc_tm')->default(0);
                $table->integer('max_accounts_sc_dtd')->default(0);
                $table->integer('max_accounts_sc_retail')->default(0);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'brand_states', function (Blueprint $table) {
                $table->dropColumn(
                    [
                        'max_accounts_res_tm',
                        'max_accounts_res_dtd',
                        'max_accounts_res_retail',
                        'max_accounts_sc_tm',
                        'max_accounts_sc_dtd',
                        'max_accounts_sc_retail',
                    ]
                );
            }
        );
    }
}
