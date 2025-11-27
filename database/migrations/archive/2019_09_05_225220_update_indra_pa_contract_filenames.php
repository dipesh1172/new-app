<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateIndraPaContractFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'indra_pa_tiered_dual_english_20190621.pdf' => 'indra_pa_tiered_dual_english_20190905.pdf',
            'indra_pa_tiered_dual_spanish_20190621.pdf' => 'indra_pa_tiered_dual_spanish_20190905.pdf',
            'indra_pa_tiered_gas_english_20190611.pdf' => 'indra_pa_tiered_gas_english_20190905.pdf',
            'indra_pa_tiered_gas_spanish_20190611.pdf' => 'indra_pa_tiered_gas_spanish_20190905.pdf',
            'indra_pa_variable_dual_english_20190717.pdf' => 'indra_pa_variable_dual_english_20190905.pdf',
            'indra_pa_variable_dual_spanish_20190717.pdf' => 'indra_pa_variable_dual_spanish_20190905.pdf',
            'indra_pa_variable_gas_english_20190717.pdf' => 'indra_pa_variable_gas_english_20190905.pdf',
            'indra_pa_variable_gas_spanish_20190717.pdf' => 'indra_pa_variable_gas_spanish_20190905.pdf',
        ];

        foreach ($contracts as $old => $new) {
            $bec = BrandEztpvContract::where(
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
