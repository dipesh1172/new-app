<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaVariableDualContracts extends Migration
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
                'pdf' => 'indra_pa_variable_dual_english_20190617.pdf',
                'sig_cust' => '{"6":{"0":"115,688,119,22"}}',
                'sig_agent' => '{"6":{"0":"115,714,119,22"}}'
            ],
            'spanish' => [
                'id' => 2,
                'pdf' => 'indra_pa_variable_dual_spanish_20190617.pdf',
                'sig_cust' => '{"6":{"0":"124,726,110,22"}}',
                'sig_agent' => '{"6":{"0":"124,752,110,22"}}'
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
                $bec->number_of_pages = 14;
                $bec->signature_required = 1;
                $bec->signature_required_customer = 1;
                $bec->signature_required_agent = 1;
                $bec->signature_info = $language['sig_cust'];
                $bec->signature_info_customer = $language['sig_cust'];
                $bec->signature_info_agent = $language['sig_agent'];
                $bec->rate_type_id = 3;
                $bec->state_id = 39;
                $bec->channel_id = $channel;
                $bec->language_id = $language['id'];
                $bec->commodity = 'dual';
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
        /V ([text_computed_gas_cancellation_fee_short])
        /T (text_computed_gas_cancellation_fee_short)
        >> 
        <<
        /V ()
        /T (signature_agent)
        >> 
        <<
        /V ([rate_info_electric_intro_rate_amount])
        /T (rate_info_electric_intro_rate_amount)
        >> 
        <<
        /V ([account_number_gas])
        /T (account_number_gas)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number_electric)
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
        /V ([rate_info_gas_intro_rate_amount])
        /T (rate_info_gas_intro_rate_amount)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name_all)
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
