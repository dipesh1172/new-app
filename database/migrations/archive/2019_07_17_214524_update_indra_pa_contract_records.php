<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateIndraPaContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_dual_english_20190617.pdf'
        )
        ->update([
            'contract_pdf' => 'indra_pa_variable_dual_english_20190717.pdf'
        ]);

        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_dual_spanish_20190617.pdf'
        )
        ->update([
            'contract_pdf' => 'indra_pa_variable_dual_spanish_20190717.pdf'
        ]);

        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_electric_english_20190613.pdf'
        )
        ->update([
            'contract_pdf' => 'indra_pa_variable_electric_english_20190717.pdf'
        ]);

        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_electric_spanish_20190613.pdf'
        )
        ->update([
            'contract_pdf' => 'indra_pa_variable_electric_spanish_20190717.pdf'
        ]);

        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_gas_english_20190613.pdf'
        )
        ->update([
            'contract_pdf' => 'indra_pa_variable_gas_english_20190717.pdf'
        ]);

        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_gas_spanish_20190613.pdf'
        )
        ->update([
            'contract_pdf' => 'indra_pa_variable_gas_spanish_20190717.pdf'
        ]);

        
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
