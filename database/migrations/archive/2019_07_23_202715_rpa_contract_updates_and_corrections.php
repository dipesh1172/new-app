<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaContractUpdatesAndCorrections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'rpa_il_dtd_residential_spanish_dual_contract_20190702.pdf' => [
                'signature_info_agent' => '{"1":{"0":"27,707,144,29"}}'
            ]
        ];

        foreach ($data as $contract => $item) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
            ->update([
                'signature_info_agent' => $item['signature_info_agent']
            ]);
        }
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
