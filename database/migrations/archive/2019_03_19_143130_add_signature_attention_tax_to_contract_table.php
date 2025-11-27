<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSignatureAttentionTaxToContractTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contract_documents', function (Blueprint $table) {
            $table->string('tpv_attention', 128);
            $table->string('tpv_federal_tax_id', 128);
            $table->longText('signature');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contract_documents', function (Blueprint $table) {
            $table->dropColumn('tpv_attention');
            $table->dropColumn('tpv_federal_tax_id');
            $table->dropColumn('signature');
        });
    }
}
