<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateBrandEztpvContractsSetSignatureRequired extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $arr = [
            'Dynegy_IL_Electric_with_UDS_2019-02-22.pdf' => 1,
            'Dynegy_OH_Electric_2019-02-20.pdf' => 1,
            'Dynegy_PA_Electric_with_UDS_2019-02-20.pdf' => 1,
            'FTE_Demo_Contract_2019_02_06.pdf' => 1,
            'GAP_Comed_819_IL_DTD_Res_electric_with_Disclosure_2018_11_26.pdf' => 1,
            'GAP_MD_Res_Contract_2018_12_05.pdf' => 1,
            'Link_DTD_dual_2018_11_16.pdf' => 1,
            'Link_DTD_site_schedule_2018_11_16.pdf' => 1,
            'Link_Retail_dual_2019_02_05.pdf' => 1,
            'Link_Retail_site_schedule_2019_02_05.pdf' => 1,
            'Link_TM_dual_2019_02_05.pdf' => 0,
            'Link_TM_site_schedule_2019_02_05.pdf' => 0,
            'Median_NJ_DTD_dual_Promotions_20181005.pdf' => 1,
            'Median_NJ_DTD_electric_Promotions_20181005.pdf' => 1,
            'Median_NJ_DTD_gas_Promotions_20181005.pdf' => 1,
            'Median_NY_DTD_any_Promotions_20181009.pdf' => 1,
            'Median_OH_DTD_any_Promotions_20190325.pdf' => 1,
            'Median_PA_DTD_dual_withSummary_withPromotions_2018_11_15.pdf' => 1,
            'Median_PA_DTD_electric_withSummary_withPromotions_2018_11_15.pdf' => 1,
            'Median_PA_DTD_gas_withSummary_withPromotions_2018_11_15.pdf' => 1,
        ];

        foreach ($arr as $pdf => $sig) {
            $bec = BrandEztpvContract::where('contract_pdf', $pdf)
                ->update([
                    'signature_required' => $sig
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
        $bec = BrandEztpvContract::update([
            'signature_required' => 1
        ]);
    }
}
