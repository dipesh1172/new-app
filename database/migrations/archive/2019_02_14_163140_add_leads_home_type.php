<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLeadsHomeType extends Migration
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
                $table->integer('home_type_id')->after('service_zip4')->nullable();
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
                $table->dropColumn('home_type_id');
            }
        );
    }
}
