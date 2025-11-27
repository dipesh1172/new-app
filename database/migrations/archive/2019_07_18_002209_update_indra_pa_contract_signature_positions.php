<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateIndraPaContractSignaturePositions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'indra_pa_variable_dual_english_20190717.pdf' => [
                'signature_info_customer' => '{"6":{"0":"116,689,117,22"}, "13":{"0":"120,609,122,22"}}',
                'signature_info_agent' => '{"6":{"0":"116,715,117,22"}, "13":{"0":"120,635,122,22"}}'
            ],
            'indra_pa_variable_dual_spanish_20190717.pdf' => [
                'signature_info_customer' => '{"6":{"0":"122,721,109,22"}, "13":{"0":"123,636,120,22"}}',
                'signature_info_agent' => '{"6":{"0":"122,747,109,22"}, "13":{"0":"123,661,121,22"}}'
            ],
            'indra_pa_variable_electric_english_20190717.pdf' => [
                'signature_info_customer' => '{"6":{"0":"116,689,117,22"}',
                'signature_info_agent' => '{"6":{"0":"116,715,117,22"}'
            ],
            'indra_pa_variable_electric_spanish_20190717.pdf' => [
                'signature_info_customer' => '{"6":{"0":"122,721,109,22"}',
                'signature_info_agent' => '{"6":{"0":"122,747,109,22"}'
            ],
            'indra_pa_variable_gas_english_20190717.pdf' => [
                'signature_info_customer' => '{"6":{"0":"120,609,122,22"}}',
                'signature_info_agent' => '{"6":{"0":"120,635,122,22"}}'
            ],
            'indra_pa_variable_gas_spanish_20190717.pdf' => [
                'signature_info_customer' => '{"6":{"0":"123,636,120,22"}}',
                'signature_info_agent' => '{"6":{"0":"123,661,121,22"}}'
            ]
        ];

        foreach ($data as $contract_pdf => $sig) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $contract_pdf
            )
            ->update([
                'signature_info' => $sig['signature_info_customer'],
                'signature_info_customer' => $sig['signature_info_customer'],
                'signature_info_agent' => $sig['signature_info_agent']
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
