<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAgentSignaturesToBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->text('signature_info_agent')->nullable()->after('signature_info');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->dropColumn('signature_info_agent');
        });
    }
}
