<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMissingSignatureFieldDataToKiwiNyContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'kiwi_ny_dtd_residential_dual_english_20190805.pdf' => [
                'number_of_pages' => 13,
                'signature_info_customer' => '{"7":{"0":"138,400,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_dual_spanish_20190805.pdf' => [
                'number_of_pages' => 14,
                'signature_info_customer' => '{"8":{"0":"123,44,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_electric_english_20190805.pdf' => [
                'number_of_pages' => 12,
                'signature_info_customer' => '{"7":{"0":"138,418,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_electric_spanish_20190805.pdf' => [
                'number_of_pages' => 13,
                'signature_info_customer' => '{"8":{"0":"122,45,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_gas_english_20190805.pdf' => [
                'number_of_pages' => 12,
                'signature_info_customer' => '{"7":{"0":"136,400,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_gas_spanish_20190805.pdf' => [
                'number_of_pages' => 13,
                'signature_info_customer' => '{"8":{"0":"122,44,180,44"}}'
            ],
        ];

        foreach ($data as $contract => $field) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )->update([
                'number_of_pages' => $field['number_of_pages'],
                'signature_required' => 1,
                'signature_required_customer' => 1,
                'signature_info' => $field['signature_info_customer'],
                'signature_info_customer' => $field['signature_info_customer'],
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
