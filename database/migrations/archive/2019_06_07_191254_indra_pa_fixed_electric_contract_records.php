<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaFixedElectricContractRecords extends Migration
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

        $channels = [
            1,
            2
        ];

        $languages = [
            'english' => [
                'id' => 1,
                'pdf' => 'indra_pa_fixed_electric_english_20190607.pdf',
                'sig_cust' => '{"6":{"0":"125,654,122,22"}}',
                'sig_agent' => '{"6":{"0":"125,680,122,22"}}'
            ],
            'spanish' => [
                'id' => 2,
                'pdf' => 'indra_pa_fixed_electric_spanish_20190607.pdf',
                'sig_cust' => '{"6":{"0":"114,671,122,22"}}',
                'sig_agent' => '{"6":{"0":"114,697,122,22"}}'
            ]
        ];

        foreach ($channels as $channel) {
            foreach ($languages as $language) {
                $bec = new BrandEztpvContract;
                $bec->brand_id = $brand->id;
                $bec->document_type_id = 1;
                $bec->contract_pdf = $language['pdf'];
                $bec->contract_fdf = $this->fdf();
                $bec->page_size = 'Letter';
                $bec->number_of_pages = 7;
                $bec->signature_required = 1;
                $bec->signature_required_customer = 1;
                $bec->signature_required_agent = 1;
                $bec->signature_info = $language['sig_cust'];
                $bec->signature_info_customer = $language['sig_cust'];
                $bec->signature_info_agent = $language['sig_agent'];
                $bec->rate_type_id = 1;
                $bec->state_id = 39;
                $bec->channel_id = $channel;
                $bec->language_id = $language['id'];
                $bec->commodity = 'electric';
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

    private function fdf()
    {
        return '<<
        /V ([address_service])
        /T (address_service)
        >> 
        <<
        /V ([bill_fullname_fl])
        /T (bill_fullname_fl)
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
        /V ([green_percentage])
        /T (green_percentage)
        >> 
        <<
        /V ([green_percentage_2])
        /T (green_percentage_2)
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
