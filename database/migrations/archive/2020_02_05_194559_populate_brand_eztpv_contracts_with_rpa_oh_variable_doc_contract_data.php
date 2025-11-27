<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PopulateBrandEztpvContractsWithRpaOhVariableDocContractData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $brand = Brand::where(
            'name',
            'RPA Energy'
        )->first();

        $channels = [
            1,
            3
        ];

        $commodities = [
            'dual',
            'electric',
            'gas'
        ];

        foreach ($channels as $channel) {
            foreach ($commodities as $commodity) {
                $bec = new BrandEztpvContract();
                $bec->brand_id = $brand->id;
                $bec->document_type_id = 1;
                $bec->document_file_type_id = 2;
                $bec->contract_pdf = 'rpa_oh_res_dtd_dual_variable_english_custom_20200205.docx';
                $bec->contract_fdf = 'none';
                $bec->signature_required = 1;
                $bec->signature_required_customer = 1;
                $bec->signature_info = 'none';
                $bec->signature_info_customer = 'none';
                $bec->signature_required_agent = 1;
                $bec->signature_info_agent = 'none';
                $bec->rate_type_id = 2;
                $bec->state_id = 36;
                $bec->channel_id = $channel;
                $bec->market_id = 1;
                $bec->language_id = 1;
                $bec->commodity = $commodity;
                $bec->save();
            }
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
