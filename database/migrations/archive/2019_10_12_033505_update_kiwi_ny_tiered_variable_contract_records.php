<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateKiwiNyTieredVariableContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'kiwi_ny_dtd_residential_variable_dual_english_20190805.pdf' => [
                'contract_pdf' => 'kiwi_ny_dtd_residential_variable_dual_english_20191011.pdf',
                'number_of_pages' => 14,
                'signature_info_customer' => '{"8":{"0":"138,478,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_variable_dual_spanish_20190805.pdf' => [
                'contract_pdf' => 'kiwi_ny_dtd_residential_variable_dual_spanish_20191011.pdf',
                'number_of_pages' => 15,
                'signature_info_customer' => '{"9":{"0":"116,353,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_variable_electric_english_20190805.pdf' => [
                'contract_pdf' => 'kiwi_ny_dtd_residential_variable_electric_english_20191011.pdf',
                'number_of_pages' => 13,
                'signature_info_customer' => '{"8":{"0":"137,478,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_variable_electric_spanish_20190805.pdf' => [
                'contract_pdf' => 'kiwi_ny_dtd_residential_variable_electric_spanish_20191011.pdf',
                'number_of_pages' => 14,
                'signature_info_customer' => '{"9":{"0":"117,353,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_variable_gas_english_20190805.pdf' => [
                'contract_pdf' => 'kiwi_ny_dtd_residential_variable_gas_english_20191011.pdf',
                'number_of_pages' => 13,
                'signature_info_customer' => '{"8":{"0":"137,478,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_variable_gas_spanish_20190805.pdf' => [
                'contract_pdf' => 'kiwi_ny_dtd_residential_variable_gas_spanish_20191011.pdf',
                'number_of_pages' => 14,
                'signature_info_customer' => '{"9":{"0":"117,353,180,44"}}'
            ],
        ];

        foreach ($contracts as $old => $new) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
                ->update([
                    'contract_pdf' => $new['contract_pdf'],
                    'number_of_pages' => $new['number_of_pages'],
                    'signature_info' => $new['signature_info_customer'],
                    'signature_info_customer' => $new['signature_info_customer']
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
