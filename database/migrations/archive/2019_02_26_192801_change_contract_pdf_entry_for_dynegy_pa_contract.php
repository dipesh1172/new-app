<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeContractPdfEntryForDynegyPaContract extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = BrandEztpvContract::where('contract_pdf', 'Dynegy_PA_Electric_2019-02-20.pdf')->first();
        $bec->contract_pdf = 'Dynegy_PA_Electric_with_UDS_2019-02-20.pdf';
        $bec->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
