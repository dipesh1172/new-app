<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateIndraIlEletricTieredEnglishSigpageContractDbRecords extends Migration
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
            'indra_il_res_allChannel_electric_tiered_english_sigpage_20200224.docx'
        )
        ->get();

        foreach ($becs as $bec) {
            $newRow = $bec->replicate();
            $newRow->contract_pdf = 'indra_il_res_allChannel_electric_tiered_english_sigpage_20200226.docx';
            $newRow->save();
        }

        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'indra_il_res_allChannel_electric_tiered_english_sigpage_20200224.docx'
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
