<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class KiwiOhContractSignaturePositionFix extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'kiwi_oh_dtd_residential_gas_spanish_20190703.pdf' => [
                'signature_info_customer' => '{"10":{"0":"332,361,180,44"}}'
            ],
            'kiwi_oh_dtd_residential_gas_english_20190703.pdf' => [
                'signature_info_customer' => '{"9":{"0":"353,521,180,44"}}'
            ],
            'kiwi_oh_dtd_residential_electric_spanish_20190703.pdf' => [
                'signature_info_customer' => '{"10":{"0":"333,361,180,44"}}'
            ],
            'kiwi_oh_dtd_residential_electric_english_20190703.pdf' => [
                'signature_info_customer' => '{"9":{"0":"352,520,180,44"}}'
            ],
            'kiwi_oh_dtd_residential_dual_spanish_20190703.pdf' => [
                'signature_info_customer' => '{"11":{"0":"332,362,180,44"}}'
            ],
            'kiwi_oh_dtd_residential_dual_english_20190703.pdf' => [
                'signature_info_customer' => '{"10":{"0":"353,520,180,44"}}'
            ]
        ];

        foreach ($data as $contract => $sig) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
            ->update([
                'signature_info' => $sig['signature_info_customer'],
                'signature_info_customer' => $sig['signature_info_customer']
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
