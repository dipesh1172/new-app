<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixSpringMdContractSelectionByRateType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'spring_md_res_tiered_dual_english_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered-variable_dual_english_20190822.pdf'
            ],
            'spring_md_res_tiered_dual_spanish_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered-variable_dual_spanish_20190822.pdf'
            ],
            'spring_md_res_tiered_electric_english_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered-variable_electric_english_20190822.pdf'
            ],
            'spring_md_res_tiered_electric_spanish_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered-variable_electric_spanish_20190822.pdf'
            ],
            'spring_md_res_tiered_gas_english_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered-variable_gas_english_20190822.pdf'
            ],
            'spring_md_res_tiered_gas_spanish_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered-variable_gas_spanish_20190822.pdf'
            ]
        ];

        foreach ($contracts as $old => $new) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
            ->update([
                'contract_pdf' => $new['contract_pdf']
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
        // unnecesssary
    }
}
