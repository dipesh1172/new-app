<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CorrectPageNumbersForSpringPaContractSignatures extends Migration
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
                'signature_info_customer' => '{"11":{"0":"170,432,180,44"}}'
            ],
            'spring_pa_res_dual_spanish_20190805.pdf' => [
                'signature_info_customer' => '{"12":{"0":"151,588,180,44"}}'
            ],
            'spring_pa_res_electric_english_20190805.pdf' => [
                'signature_info_customer' => '{"10":{"0":"171,432,180,44"}}'
            ],
            'spring_pa_res_electric_spanish_20190805.pdf' => [
                'signature_info_customer' => '{"11":{"0":"155,590,180,44"}}'
            ],
            'spring_pa_res_gas_english_20190805.pdf' => [
                'signature_info_customer' => '{"10":{"0":"170,432,180,44"}}'
            ],
            'spring_pa_res_gas_spanish_20190805.pdf' => [
                'signature_info_customer' => '{"11":{"0":"154,590,180,44"}}'
            ],
        ];

        foreach ($data as $contract => $field) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )->update([
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
        //
    }
}
