<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLeadsZip4 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'leads',
            function (Blueprint $table) {
                $table->string('service_zip4', 4)->after('service_zip')->nullable();
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
            'leads',
            function (Blueprint $table) {
                $table->dropColumn('service_zip4');
            }
        );
    }
}
