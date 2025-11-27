<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixRpaContractPageCounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_il_res_dtd_dual_english_custom_20191017.pdf' => [
                'number_of_pages' => 8
            ],
            'rpa_il_res_dtd_dual_spanish_custom_20191017.pdf' => [
                'number_of_pages' => 10
            ],
            'rpa_md_dtd_residential_dual_english_20191017.pdf' => [
                'number_of_pages' => 6
            ],
            'rpa_md_res_dtd_dual_spanish_custom_20191017.pdf' => [
                'number_of_pages' => 6
            ],
            'rpa_nj_dtd_residential_dual_english_20191017.pdf' => [
                'number_of_pages' => 6
            ],
            'rpa_nj_dtd_residential_spanish_dual_contract_20191017.pdf' => [
                'number_of_pages' => 6
            ],
            'rpa_oh_dtd_residential_dual_english_20191017.pdf' => [
                'number_of_pages' => 6
            ],
            'rpa_oh_res_dtd_dual_spanish_custom_20191017.pdf' => [
                'number_of_pages' => 6
            ],
            'rpa_pa_dtd_residential_dual_english_20191017.pdf' => [
                'number_of_pages' => 8
            ],
            'rpa_pa_dtd_residential_spanish_dual_contract_20191017.pdf' => [
                'number_of_pages' => 9
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
                ->update([
                    'number_of_pages' => $data['number_of_pages']
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
