<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProposalDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('brand_id', 128);
            $table->string('vendor_id', 128);
            $table->string('po_number', 128)->nullable();
            $table->string('po_amount', 128)->nullable();
            $table->string('contact_name', 128);
            $table->string('contact_email', 128);
            $table->string('contact_phone', 128);
            $table->string('company_name', 128);
            $table->string('company_address');
            $table->string('company_city', 128);
            $table->string('company_state', 128);
            $table->string('company_zip', 128);
            $table->string('company_tax', 128);
            $table->string('company_approver', 128);
            $table->string('company_title', 128);
            $table->string('company_email', 128);
            $table->string('company_date', 128);
            $table->string('company_signature');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
