<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLeadMiddleName extends Migration
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
                $table->string('middle_name', 64)->after('first_name')->nullable();
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
                $table->dropColumn('middle_name');
            }
        );
    }
}
