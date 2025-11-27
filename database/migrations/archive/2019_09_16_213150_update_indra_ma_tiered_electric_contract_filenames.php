<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateIndraMaTieredElectricContractFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'indra_ma_res_dtd_electric_english_tiered_sigpage_20190905.pdf' => 'indra_ma_res_dtd_electric_english_tiered_sigpage_20190909.pdf',
            'indra_ma_res_dtd_electric_spanish_tiered_sigpage_20190905.pdf' => 'indra_ma_res_dtd_electric_spanish_tiered_sigpage_20190909.pdf',
        ];
        
        foreach ($contracts as $old => $new) {
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
        // unnecessary
    }
}
