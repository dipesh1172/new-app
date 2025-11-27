<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixIndraMdContractRecords extends Migration
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
            'Indra%'
        )
        ->first();

        //  template
        // '' => [
        //     'contract_fdf' => $this->,
        //     'page_size' => '',
        //     'number_of_pages' => ,
        //     'signature_info_customer' => '',
        //     'signature_info_agent' => '',
        //     'rate_type_id' => ,
        //     'language_id' => ,
        //     'commodity' => ''
        // ],
        $data = [
            'indra_md_fixed_electric_english_20190726.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 6,
                'signature_info_customer' => '{"5":{"0":"157,570,120,24"}}',
                'signature_info_agent' => '{"5":{"0":"157,595,122,24"}}',
                'rate_type_id' => 1,
                'language_id' => 1,
                'commodity' => 'electric'
            ],
            'indra_md_fixed_electric_spanish_20190726.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 6,
                'signature_info_customer' => '{"5":{"0":"178,676,120,24"}}',
                'signature_info_agent' => '{"5":{"0":"178,702,122,24"}}',
                'rate_type_id' => 1,
                'language_id' => 1,
                'commodity' => 'electric'
            ]
        ];

        foreach ($data as $contract => $info) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 1;
            $bec->contract_pdf = $contract;
            $bec->contract_fdf = $this->fixed_fdf();
            $bec->page_size = $info['page_size'];
            $bec->number_of_pages = $info['number_of_pages'];
            $bec->signature_required = 1;
            $bec->signature_required_customer = 1;
            $bec->signature_info = $info['signature_info_customer'];
            $bec->signature_info_customer = $info['signature_info_customer'];
            $bec->signature_required_agent = 1;
            $bec->signature_info_agent = $info['signature_info_agent'];
            $bec->rate_type_id = $info['rate_type_id'];
            $bec->state_id = 21;
            $bec->channel_id = 1;
            $bec->language_id = $info['language_id'];
            $bec->commodity = $info['commodity'];
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

    private function fixed_fdf()
    {
        return '<<
        /V ([address_service])
        /T (address_service)
        >> 
        <<
        /V ([green_percentage_formatted])
        /T (green_percentage_formatted)
        >> 
        <<
        /V ([computed_multiline_auth_fullname_fl_plus_service_address])
        /T (computed_multiline_auth_fullname_fl_plus_service_address)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
        /T (rate_info_electric_calculated_rate_amount)
        >> 
        <<
        /V ()
        /T (signature_agent)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number_electric)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name_all)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([city_state_zip_service])
        /T (city_state_zip_service)
        >> 
        <<
        /V ([text_computed_electric_cancellation_fee_short])
        /T (text_computed_electric_cancellation_fee_short)
        >>';
    }
}
