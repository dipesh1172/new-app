<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixRpaIlEnglishContractSigPositions extends Migration
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
            'rpa_il%english%'
        )
            ->update([
                'signature_info' => '{"1":{"0":"37,854,190,35"}}',
                'signature_info_customer' => '{"1":{"0":"37,854,190,35"}}',
                'signature_info_agent' => '{"1":{"0":"37,906,190,35"}}'
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
