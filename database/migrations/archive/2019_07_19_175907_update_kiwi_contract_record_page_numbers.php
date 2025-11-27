<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateKiwiContractRecordPageNumbers extends Migration
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
                'number_of_pages' => 15
            ],
            'kiwi_oh_dtd_residential_gas_english_20190703.pdf' => [
                'number_of_pages' => 14
            ],
            'kiwi_oh_dtd_residential_electric_spanish_20190703.pdf' => [
                'number_of_pages' => 15
            ],
            'kiwi_oh_dtd_residential_electric_english_20190703.pdf' => [
                'number_of_pages' => 14
            ],
            'kiwi_oh_dtd_residential_dual_spanish_20190703.pdf' => [
                'number_of_pages' => 17
            ],
            'kiwi_oh_dtd_residential_dual_english_20190703.pdf' => [
                'number_of_pages' => 16
            ]
        ];

        foreach ($data as $contract => $datapoint) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
            ->update([
                'number_of_pages' => $datapoint['number_of_pages']
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
