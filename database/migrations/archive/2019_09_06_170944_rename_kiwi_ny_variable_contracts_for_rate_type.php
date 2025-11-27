<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameKiwiNyVariableContractsForRateType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'kiwi_ny_dtd_residential_dual_english_20190805.pdf' => 'kiwi_ny_dtd_residential_variable_dual_english_20190805.pdf',
            'kiwi_ny_dtd_residential_dual_spanish_20190805.pdf' => 'kiwi_ny_dtd_residential_variable_dual_spanish_20190805.pdf',
            'kiwi_ny_dtd_residential_electric_english_20190805.pdf' => 'kiwi_ny_dtd_residential_variable_electric_english_20190805.pdf',
            'kiwi_ny_dtd_residential_electric_spanish_20190805.pdf' => 'kiwi_ny_dtd_residential_variable_electric_spanish_20190805.pdf',
            'kiwi_ny_dtd_residential_gas_english_20190805.pdf' => 'kiwi_ny_dtd_residential_variable_gas_english_20190805.pdf',
            'kiwi_ny_dtd_residential_gas_spanish_20190805.pdf' => 'kiwi_ny_dtd_residential_variable_gas_spanish_20190805.pdf',
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
