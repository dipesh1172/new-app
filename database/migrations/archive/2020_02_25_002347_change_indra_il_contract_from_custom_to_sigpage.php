<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeIndraIlContractFromCustomToSigpage extends Migration
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
            'indra_il_res_allChannel_electric_tiered_english_custom_20200224.docx'
        )
        ->update([
            'document_type_id' => 3,
            'contract_pdf' => 'indra_il_res_allChannel_electric_tiered_english_sigpage_20200224.docx'
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
