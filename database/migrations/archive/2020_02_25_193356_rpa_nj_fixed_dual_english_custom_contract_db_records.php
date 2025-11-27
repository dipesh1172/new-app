<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaNjFixedDualEnglishCustomContractDbRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // soft-delete old records
        $oldies = [
            'rpa_nj_dtd_residential_dual_english_20191017.pdf',
            'rpa_nj_dtd_residential_spanish_dual_contract_20191017.pdf'
        ];

        foreach ($oldies as $oldy) {
            $bec_remove = BrandEztpvContract::where(
                'contract_pdf',
                $oldy
            )
            ->update([
                'deleted_at' => NOW()
            ]);
        }

        // add new records
        $brand = Brand::where(
            'name',
            'RPA Energy'
        )
            ->first();

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
                $bec = new BrandEztpvContract;
                $bec->brand_id = $brand->id;
                $bec->document_type_id = 1;
                $bec->document_file_type_id = 2;
                $bec->contract_pdf = 'rpa_nj_allMarket_allChannel_dual_fixed_english_custom_20200225.docx';
                $bec->contract_fdf = 'none';
                $bec->signature_required = 1;
                $bec->signature_required_customer = 1;
                $bec->signature_info = 'none';
                $bec->signature_info_customer = 'none';
                $bec->signature_required_agent = 1;
                $bec->signature_info_agent = 'none';
                $bec->rate_type_id = 1;
                $bec->state_id = 31;
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
