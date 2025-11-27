<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRpaContractFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_il_res_dtd_dual_english_custom_20190913.pdf' => 'rpa_il_res_dtd_dual_english_custom_20190930.pdf',
            'rpa_il_res_dtd_dual_spanish_custom_20190913.pdf' => 'rpa_il_res_dtd_dual_spanish_custom_20190930.pdf',
            'rpa_md_dtd_residential_dual_2019_06_05.pdf' => 'rpa_md_dtd_residential_dual_20190930.pdf',
            'rpa_md_res_dtd_dual_spanish_custom_20190919.pdf' => 'rpa_md_res_dtd_dual_spanish_custom_20190930.pdf',
            'rpa_nj_dtd_residential_dual_2019_05_28.pdf' => 'rpa_nj_dtd_residential_dual_20190930.pdf',
            'rpa_nj_dtd_residential_spanish_dual_contract_20190702.pdf' => 'rpa_nj_dtd_residential_spanish_dual_contract_20190930.pdf',
            'rpa_oh_dtd_residential_dual_2019_05_29.pdf' => 'rpa_oh_dtd_residential_dual_20190930.pdf',
            'rpa_oh_res_dtd_dual_spanish_custom_20190919.pdf' => 'rpa_oh_res_dtd_dual_spanish_custom_20190930.pdf',
            'rpa_pa_dtd_residential_dual_2019_05_31.pdf' => 'rpa_pa_dtd_residential_dual_20190930.pdf',
            'rpa_pa_dtd_residential_spanish_dual_contract_20190702.pdf' => 'rpa_pa_dtd_residential_spanish_dual_contract_20190930.pdf'
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
