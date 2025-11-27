<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AdjustSignaturePositioningOnRpaMdContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_md_dtd_residential_dual_20190930.pdf' => [
                'customer' => '{"1":{"0":"82,787,190,45"}}',
                'agent' => '{"1":{"0":"82,853,190,45"}}'
            ],
            'rpa_md_res_dtd_dual_spanish_custom_20190930.pdf' => [
                'customer' => '{"1":{"0":"30,717,160,30"}}',
                'agent' => '{"1":{"0":"30,756,160,30"}}'
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
                ->update([
                    'signature_info' => $data['customer'],
                    'signature_info_customer' => $data['customer'],
                    'signature_info_agent' => $data['agent'],
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
