<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRecordsForGreenlightPaFixedDualEnglishSigpageContract extends Migration
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
            'Greenlight Energy'
        )
            ->first();

        $commodities = [
            'dual',
            'electric',
            'gas'
        ];

        $channels = [
            1,
            3
        ];

        foreach ($channels as $channel) {
            foreach ($commodities as $commodity) {
                $bec = new BrandEztpvContract;
                $bec->brand_id = $brand->id;
                $bec->document_type_id = 3;
                $bec->contract_pdf = 'greenlight_pa_dual_fixed_english_sigpage_contract_09042019.pdf';
                $bec->page_size = 'Letter';
                $bec->number_of_pages = 1;
                $bec->signature_required = 1;
                $bec->signature_required_customer = 1;
                $bec->signature_required_agent = 1;
                $bec->rate_type_id = 1;
                $bec->state_id = 39;
                $bec->channel_id = $channel;
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
        //
    }
}
