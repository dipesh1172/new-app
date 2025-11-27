<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SetOriginalContractFieldForClearviewIlLoyaltyassurance12plusContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $orig = BrandEztpvContract::where(
            'contract_pdf',
            'clearview_il_LoyaltyAssurance12Plus_sigpage_20200225.docx'
        )
        ->first();

        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'clearview_il_LoyaltyAssurance12Plus_sigpage_20200225.docx'
        )
        ->update([
            'original_contract' => $orig->id
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
