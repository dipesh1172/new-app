<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVendorsVendorCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'vendors', function (Blueprint $table) {
                $table->string('vendor_code', 32)->nullable()->after('vendor_label');
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
            'vendors',
            function (Blueprint $table) {
                $table->dropColumn('vendor_code');
            }
        );
    }
}
