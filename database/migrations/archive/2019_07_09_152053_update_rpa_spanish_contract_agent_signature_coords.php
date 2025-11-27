<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateRpaSpanishContractAgentSignatureCoords extends Migration
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
            'LIKE',
            'rpa_rpa_il_dtd_residential_spanish_dual_contract_20190702.pdf'
        )
        ->update([
            'signature_info_agent' => '{"1":{"0":"27,707,144,29"}}'
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
