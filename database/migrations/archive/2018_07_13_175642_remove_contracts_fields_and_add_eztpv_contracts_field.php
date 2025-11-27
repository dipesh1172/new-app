<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveContractsFieldsAndAddEztpvContractsField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_states', function (Blueprint $table) {
            $table->dropColumn('eztpv_pdf_contracts');
            $table->dropColumn('eztpv_photo_contracts');
            $table->string('eztpv_contracts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_states', function (Blueprint $table) {
            $table->dropColumn('eztpv_contracts');
            $table->tinyInteger('eztpv_photo_contracts');
            $table->tinyInteger('eztpv_pdf_contracts');
        });
    }
}
