<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SpringMdFixedElectricEnglishContractRecords extends Migration
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
            'LIKE',
            'Spring Power%Gas'
        )
        ->first();

        $channels = [
            1,
            3
        ];

        foreach ($channels as $channel) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 1;
            $bec->contract_pdf = 'spring_md_res_dtd_fixed_electric_english_custom_20190919.pdf';
            $bec->contract_fdf = $this->fdf();
            $bec->page_size = 'Letter';
            $bec->number_of_pages = 15;
            $bec->signature_required = 1;
            $bec->signature_required_customer = 1;
            $bec->signature_info = '{"11":{"0":"168,310,180,44"}}';
            $bec->signature_info_customer = '{"11":{"0":"168,310,180,44"}}';
            $bec->signature_required_agent = 0;
            $bec->rate_type_id = 1;
            $bec->state_id = 21;
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
        // unnecessary
    }

    public function fdf() {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >>';
    }
}
