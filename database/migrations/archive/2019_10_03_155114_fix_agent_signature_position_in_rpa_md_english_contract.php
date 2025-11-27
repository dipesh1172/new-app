<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixAgentSignaturePositionInRpaMdEnglishContract extends Migration
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
            'rpa_md_dtd_residential_dual_20190930.pdf'
        )
            ->update([
                'signature_info' => '{"1":{"0":"82,787,190,45"}}',
                'signature_info_customer' => '{"1":{"0":"82,787,190,45"}}',
                'signature_info_agent' => '{"1":{"0":"82,851,190,45"}}'
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
