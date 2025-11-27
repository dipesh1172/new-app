<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixRateTypeIdsForKiwiContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'LIKE',
            'kiwi_ny%variable%'
        )
            ->whereNull(
                'rate_type_id'
            )
            ->update([
                'rate_type_id' => 3
            ]);

        $ohContracts = [
            'kiwi_oh_dtd_residential_dual_english_20190806.pdf' => 'kiwi_oh_dtd_residential_variable_dual_english_20190806.pdf',
            'kiwi_oh_dtd_residential_dual_spanish_20190806.pdf' => 'kiwi_oh_dtd_residential_variable_dual_spanish_20190806.pdf',
            'kiwi_oh_dtd_residential_electric_english_20190806.pdf' => 'kiwi_oh_dtd_residential_variable_electric_english_20190806.pdf',
            'kiwi_oh_dtd_residential_electric_spanish_20190806.pdf' => 'kiwi_oh_dtd_residential_variable_electric_spanish_20190806.pdf',
            'kiwi_oh_dtd_residential_gas_english_20190806.pdf' => 'kiwi_oh_dtd_residential_variable_gas_english_20190806.pdf',
            'kiwi_oh_dtd_residential_gas_spanish_20190806.pdf' => 'kiwi_oh_dtd_residential_variable_gas_spanish_20190806.pdf',
        ];

        foreach ($ohContracts as $old => $new) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
                ->update([
                    'contract_pdf' => $new,
                    'rate_type_id' => 3
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
