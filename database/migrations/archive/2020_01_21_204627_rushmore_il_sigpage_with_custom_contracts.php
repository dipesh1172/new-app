<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RushmoreIlSigpageWithCustomContracts extends Migration
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
            'Rushmore Energy'
        )
            ->first();

        $channels = [
            1,
            3
        ];

        foreach ($channels as $channel) {
            $bec = new BrandEztpvContract();
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 3;
            $bec->document_file_type_id = 2;
            $bec->contract_pdf = 'rushmore_il_allChannel_residential_english_fixed_electric_sigpage_contract_20200121.docx';
            $bec->contract_fdf = 'none';
            $bec->signature_required = 0;
            $bec->signature_required_customer = 0;
            $bec->signature_required_agent = 0;
            $bec->rate_type_id = 1;
            $bec->state_id = 14;
            $bec->channel_id = $channel;
            $bec->language_id = 1;
            $bec->commodity = 'electric';
            $bec->save();
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
