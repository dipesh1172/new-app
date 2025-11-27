<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMissingSignatureFieldDataToSpringPaContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'spring_pa_res_dual_english_20190805.pdf' => [
                'number_of_pages' => 16,
                'signature_info_customer' => '{"6":{"0":"170,432,180,44"}}'
            ],
            'spring_pa_res_dual_spanish_20190805.pdf' => [
                'number_of_pages' => 17,
                'signature_info_customer' => '{"6":{"0":"151,588,180,44"}}'
            ],
            'spring_pa_res_electric_english_20190805.pdf' => [
                'number_of_pages' => 14,
                'signature_info_customer' => '{"6":{"0":"171,432,180,44"}}'
            ],
            'spring_pa_res_electric_spanish_20190805.pdf' => [
                'number_of_pages' => 15,
                'signature_info_customer' => '{"6":{"0":"155,590,180,44"}}'
            ],
            'spring_pa_res_gas_english_20190805.pdf' => [
                'number_of_pages' => 14,
                'signature_info_customer' => '{"6":{"0":"170,432,180,44"}}'
            ],
            'spring_pa_res_gas_spanish_20190805.pdf' => [
                'number_of_pages' => 15,
                'signature_info_customer' => '{"6":{"0":"154,590,180,44"}}'
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
