<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaPaContractRecordCorrections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'rpa_pa_dtd_residential_dual_2019_05_31.pdf' => [
                'signature_info_customer' => '{"1":{"0":"36,797,200,40"}}'
            ],
            'rpa_il_dtd_residential_dual_2019_05_28.pdf' => [
                'signature_info_customer' => '{"1":{"0":"37,865,190,35"}}'
            ]
        ];

        foreach ($data as $pdf => $sig)
        {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $pdf
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
