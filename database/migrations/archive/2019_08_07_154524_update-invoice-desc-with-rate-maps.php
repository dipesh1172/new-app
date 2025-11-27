<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateInvoiceDescWithRateMaps extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::transaction(function () {
            DB::table('invoice_desc')->where('item_desc', 'Live Minutes')->update(['map_rate_to' => 'live_flat_rate']);
            DB::table('invoice_desc')->where('item_desc', 'IVR Minutes')->update(['map_rate_to' => 'ivr_rate']);
            DB::table('invoice_desc')->where('item_desc', 'DNIS (Tollfree)')->update(['map_rate_to' => 'did_tollfree']);
            DB::table('invoice_desc')->where('item_desc', 'DNIS (Local)')->update(['map_rate_to' => 'did_local']);
            DB::table('invoice_desc')->where('item_desc', 'Cloud Storage')->update(['map_rate_to' => 'storage_rate_in_gb']);
            DB::table('invoice_desc')->where('item_desc', 'Custom Reports')->update(['map_rate_to' => 'custom_report_fee']);
            DB::table('invoice_desc')->where('item_desc', 'Long Distance (domestic)')->update(['map_rate_to' => 'ld_billback_dom']);
            DB::table('invoice_desc')->where('item_desc', 'Long Distance (international)')->update(['map_rate_to' => 'ld_billback_intl']);
            //DB::table('invoice_desc')->where('item_desc', 'SMS/Text Delivery')->update(['map_rate_to' => '']);
            DB::table('invoice_desc')->where('item_desc', 'VOIP Lookup')->update(['map_rate_to' => 'cell_number_verification']);
            DB::table('invoice_desc')->where('item_desc', 'Digital TPV')->update(['map_rate_to' => 'digital_transaction']);
            DB::table('invoice_desc')->where('item_desc', 'Document Services (contracts)')->update(['map_rate_to' => 'contract_review']);
            DB::table('invoice_desc')->where('item_desc', 'EZTPV (DTD)')->update(['map_rate_to' => 'eztpv_rate']);
            DB::table('invoice_desc')->where('item_desc', 'EZTPV (Retail)')->update(['map_rate_to' => 'eztpv_rate']);
            DB::table('invoice_desc')->where('item_desc', 'EZTPV (TM)')->update(['map_rate_to' => 'eztpv_tm_rate']);
            DB::table('invoice_desc')->where('item_desc', 'Custom Development')->update(['map_rate_to' => 'it_billable']);
            DB::table('invoice_desc')->where('item_desc', 'Client Services')->update(['map_rate_to' => 'cs_billable']);
            DB::table('invoice_desc')->where('item_desc', 'Quality Assurance')->update(['map_rate_to' => 'qa_billable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
