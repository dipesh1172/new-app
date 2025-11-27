<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateKiwiOhContractRecordsWithNewFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'kiwi_oh_dtd_residential_dual_english_20190703.pdf' => 'kiwi_oh_dtd_residential_dual_english_20190806.pdf',
            'kiwi_oh_dtd_residential_dual_spanish_20190703.pdf' => 'kiwi_oh_dtd_residential_dual_spanish_20190806.pdf',
            'kiwi_oh_dtd_residential_electric_english_20190703.pdf' => 'kiwi_oh_dtd_residential_electric_english_20190806.pdf',
            'kiwi_oh_dtd_residential_electric_spanish_20190703.pdf' => 'kiwi_oh_dtd_residential_electric_spanish_20190806.pdf',
            'kiwi_oh_dtd_residential_gas_english_20190703.pdf' => 'kiwi_oh_dtd_residential_gas_english_20190806.pdf',
            'kiwi_oh_dtd_residential_gas_spanish_20190703.pdf' => 'kiwi_oh_dtd_residential_gas_spanish_20190806.pdf'
        ];

        foreach ($data as $old => $new) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )->update([
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
        //
    }
}
