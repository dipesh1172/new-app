<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSoftdeletePurchaseToProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal_documents', function (Blueprint $table) {
            $table->string('purchase_order', 128);
            $table->longText('company_signature')->change();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('proposal_documents', function (Blueprint $table) {
            $table->dropColumn('purchase_order');
            $table->dropColumn('company_signature');
            $table->dropSoftDeletes();
        });
    }
}
