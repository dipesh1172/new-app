<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRpaContractsWithMarketingSlides extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_il_res_dtd_dual_english_custom_20190930.pdf' => [
                'contract_pdf' => 'rpa_il_res_dtd_dual_english_custom_20191017.pdf',
                'number_of_pages' => 10
            ],
            'rpa_il_res_dtd_dual_spanish_custom_20190930.pdf' => [
                'contract_pdf' => 'rpa_il_res_dtd_dual_spanish_custom_20191017.pdf',
                'number_of_pages' => 12
            ],
            'rpa_md_dtd_residential_dual_20190930.pdf' => [
                'contract_pdf' => 'rpa_md_dtd_residential_dual_english_20191017.pdf',
                'number_of_pages' => 8
            ],
            'rpa_md_res_dtd_dual_spanish_custom_20190930.pdf' => [
                'contract_pdf' => 'rpa_md_res_dtd_dual_spanish_custom_20191017.pdf',
                'number_of_pages' => 8
            ],
            'rpa_nj_dtd_residential_dual_20190930.pdf' => [
                'contract_pdf' => 'rpa_nj_dtd_residential_dual_english_20191017.pdf',
                'number_of_pages' => 8
            ],
            'rpa_nj_dtd_residential_spanish_dual_contract_20190930.pdf' => [
                'contract_pdf' => 'rpa_nj_dtd_residential_spanish_dual_contract_20191017.pdf',
                'number_of_pages' => 8
            ],
            'rpa_oh_dtd_residential_dual_20190930.pdf' => [
                'contract_pdf' => 'rpa_oh_dtd_residential_dual_english_20191017.pdf',
                'number_of_pages' => 8
            ],
            'rpa_oh_res_dtd_dual_spanish_custom_20190930.pdf' => [
                'contract_pdf' => 'rpa_oh_res_dtd_dual_spanish_custom_20191017.pdf',
                'number_of_pages' => 8
            ],
            'rpa_pa_dtd_residential_dual_20190930.pdf' => [
                'contract_pdf' => 'rpa_pa_dtd_residential_dual_english_20191017.pdf',
                'number_of_pages' => 10
            ],
            'rpa_pa_dtd_residential_spanish_dual_contract_20190930.pdf' => [
                'contract_pdf' => 'rpa_pa_dtd_residential_spanish_dual_contract_20191017.pdf',
                'number_of_pages' => 11
            ]
        ];

        foreach ($contracts as $old => $new) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
                ->update([
                    'contract_pdf' => $new['contract_pdf'],
                    'number_of_pages' => $new['number_of_pages']
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
