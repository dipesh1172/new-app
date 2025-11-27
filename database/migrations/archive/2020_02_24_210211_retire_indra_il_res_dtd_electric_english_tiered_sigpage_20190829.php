<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RetireIndraIlResDtdElectricEnglishTieredSigpage20190829 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'indra_il_res_dtd_electric_english_tiered_sigpage_20190829.pdf'
        )
        ->update([
            'deleted_at' => NOW()
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // unnecessary
    }
}
