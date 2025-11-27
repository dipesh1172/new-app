<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameEztpvContractsFieldToEztpvPdfContractsAndAddEztpvPhotoContractsField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_states', function (Blueprint $table) {
            $table->renameColumn('eztpv_contracts', 'eztpv_pdf_contracts');
            $table->tinyInteger('eztpv_photo_contracts');
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
            $table->renameColumn('eztpv_pdf_contracts', 'eztpv_contracts');
            $table->dropColumn('eztpv_photo_contracts');
        });
    }
}
