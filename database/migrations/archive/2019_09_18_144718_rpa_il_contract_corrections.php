<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaIlContractCorrections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_il_res_dtd_dual_spanish_custom_20190913.pdf' => [
                'signature_info_customer' => '{"2":{"0":"32,195,130,30"}}',
                'signature_info_agent' => '{"2":{"0":"32,233,130,33"}}',
                'page_size' => 'Letter'
            ],
            'rpa_il_res_dtd_dual_english_custom_20190913.pdf' => [
                'signature_info_customer' => '{"1":{"0":"37,848,190,40"}}',
                'signature_info_agent' => '{"1":{"0":"37,901,190,40"}}',
                'page_size' => 'Legal'
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
            ->update([
                'page_size' => $data['page_size'],
                'signature_info' => $data['signature_info_customer'],
                'signature_info_customer' => $data['signature_info_customer'],
                'signature_info_agent' => $data['signature_info_agent']
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
