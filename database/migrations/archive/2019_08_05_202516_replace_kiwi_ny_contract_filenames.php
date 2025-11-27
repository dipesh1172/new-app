<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ReplaceKiwiNyContractFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'kiwi_ny_dtd_residential_dual_english_20190',
            'kiwi_ny_dtd_residential_dual_spanish_20190',
            'kiwi_ny_dtd_residential_electric_english_20190',
            'kiwi_ny_dtd_residential_electric_spanish_20190',
            'kiwi_ny_dtd_residential_gas_english_20190',
            'kiwi_ny_dtd_residential_gas_spanish_20190'
        ];
        foreach ($data as $contract)
        {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                'LIKE',
                $contract
            )->update([
                'contract_pdf' => $contract . '805.pdf'
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
