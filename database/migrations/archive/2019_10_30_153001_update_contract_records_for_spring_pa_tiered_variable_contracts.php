<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateContractRecordsForSpringPaTieredVariableContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'spring_pa_res_dual_tiered-variable_english_20190826.pdf' => 'spring_pa_res_dual_tiered-variable_english_20191030.pdf',
            'spring_pa_res_electric_tiered-variable_english_20190826.pdf' => 'spring_pa_res_electric_tiered-variable_english_20191030.pdf',
            'spring_pa_res_gas_tiered-variable_english_20190805.pdf' => 'spring_pa_res_gas_tiered-variable_english_20191030.pdf'
        ];

        foreach ($contracts as $old => $new) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
                ->update([
                    'contract_pdf' => $new
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
