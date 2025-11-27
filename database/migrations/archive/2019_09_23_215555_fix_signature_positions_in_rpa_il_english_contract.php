<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixSignaturePositionsInRpaIlEnglishContract extends Migration
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
                'signature_info' => '{"1":{"0":"37,847,190,40"}}',
                'signature_info_customer' => '{"1":{"0":"37,847,190,40"}}',
                'signature_info_agent' => '{"1":{"0":"37,900,190,40"}}'
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
