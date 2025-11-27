<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\InvoiceDesc;

class UpdateSortOrderForInvoiceDesc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $map = [
            // Group 1
            ['Live Minutes', 1],
            ['Late Fees', 2],
            ['Adjustment to Minimum', 3],
            ['Interconnect Fee (domestic)', 4],
            ['Interconnect Fee (international)', 5],
            ['Cloud Storage', 6],
            // Group 2
            ['API Submission', 7],
            ['Web Enroll', 8],
            ['EZTPV (DTD)', 9],
            ['EZTPV (Retail)', 10],
            ['EZTPV (TM)', 11],
            ['Sales Pitch Recording', 12],
            ['Digital TPV', 13],
            ['IVR', 14],
            ['IVR Voice Print', 15],
            ['Document Services (contracts)', 16],
            ['Pay Link', 17],
            ['Daily Questionnaires', 18],
            ['SMS/Text Delivery', 19],
            ['ESI ID Lookup', 20],
            ['VOIP Lookup', 21],
            ['GPS Distance (Customer <> Sales Agent)', 22],
            ['Server Hosting', 23],
            ['DNIS (Tollfree)', 24],
            ['DNIS (Local)', 25],
            // Group 3
            ['Onboarding Costs', 26],
            ['Recording Storage Deposit', 27],
            ['Custom Development', 28],
            ['Custom Reports', 29],
            ['Client Services', 30],
            ['Quality Assurance', 31],
            ['Miscellaneous', 32],
        ];

        foreach ($map as $toUpdate) {
            $id = InvoiceDesc::where('item_desc', $toUpdate[0])->first();
            $id->invoice_sort = $toUpdate[1];
            $id->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
