<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateIndraIlElectricTieredEnglishSigpageContractDbRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'indra_il_res_allChannel_electric_tiered_english_sigpage_20200226.docx'
        )
        ->update([
            'contract_pdf' => 'indra_il_res_allChannel_electric_tiered_english_sigpage_20200227.docx'
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
