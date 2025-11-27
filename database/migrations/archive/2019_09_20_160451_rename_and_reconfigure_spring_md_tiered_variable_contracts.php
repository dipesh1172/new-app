<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameAndReconfigureSpringMdTieredVariableContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'spring_md_res_dual_english_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered_dual_english_20190822.pdf',
                'rate_type_id' => 3
            ],
            'spring_md_res_dual_spanish_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered_dual_spanish_20190822.pdf',
                'rate_type_id' => 3
            ],
            'spring_md_res_electric_english_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered_electric_english_20190822.pdf',
                'rate_type_id' => 3
            ],
            'spring_md_res_electric_spanish_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered_electric_spanish_20190822.pdf',
                'rate_type_id' => 3
            ],
            'spring_md_res_gas_english_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered_gas_english_20190822.pdf',
                'rate_type_id' => 3
            ],
            'spring_md_res_gas_spanish_20190822.pdf' => [
                'contract_pdf' => 'spring_md_res_tiered_gas_spanish_20190822.pdf',
                'rate_type_id' => 3
            ]
        ];

        foreach ($contracts as $old => $new) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
            ->update([
                'contract_pdf' => $new['contract_pdf'],
                'rate_type_id' => $new['rate_type_id']
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
