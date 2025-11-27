<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDocumentFileTypeIdColumnToBrandEztpvContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->integer('document_file_type_id')->after('document_type_id');
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
            $table->dropColumn('document_file_type_id');
        });
    }
}
