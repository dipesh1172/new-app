<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaTieredContracts extends Migration
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

        $commodities = [
            'electric',
            'gas'
        ];

        $languages = [
            'english' => [
                'id' => 1,
                'electric' => [
                    'pdf' => 'indra_pa_tiered_electric_english_20190611.pdf',
                    'sig_cust' => '{"6":{"0":"121,654,122,22"}}',
                    'sig_agent' => '{"6":{"0":"121,680,122,22"}}'
                ],
                'gas' => [
                    'pdf' => 'indra_pa_tiered_gas_english_20190611.pdf',
                    'sig_cust' => '{"6":{"0":"117,502,122,22"}}',
                    'sig_agent' => '{"6":{"0":"117,528,122,22"}}'
                ]
                
            ],
            'spanish' => [
                'id' => 2,
                'electric' => [
                    'pdf' => 'indra_pa_tiered_electric_spanish_20190612.pdf',
                    'sig_cust' => '{"6":{"0":"123,709,122,22"}}',
                    'sig_agent' => '{"6":{"0":"123,735,122,22"}}'
                ],
                'gas' => [
                    'pdf' => 'indra_pa_tiered_gas_spanish_20190611.pdf',
                    'sig_cust' => '{"6":{"0":"126,612,122,22"}}',
                    'sig_agent' => '{"6":{"0":"126,638,122,22"}}'
                ]
            ]
        ];

        foreach ($channels as $channel) {
            foreach ($languages as $language) {
                foreach ($commodities as $commodity)
                {
                    $bec = new BrandEztpvContract;
                    $bec->brand_id = $brand->id;
                    $bec->document_type_id = 1;
                    $bec->contract_pdf = $language[$commodity]['pdf'];
                    $bec->contract_fdf = $this->fdf($commodity);
                    $bec->page_size = 'Letter';
                    $bec->number_of_pages = 7;
                    $bec->signature_required = 1;
                    $bec->signature_required_customer = 1;
                    $bec->signature_required_agent = 1;
                    $bec->signature_info = $language[$commodity]['sig_cust'];
                    $bec->signature_info_customer = $language[$commodity]['sig_cust'];
                    $bec->signature_info_agent = $language[$commodity]['sig_agent'];
                    $bec->rate_type_id = 1;
                    $bec->state_id = 39;
                    $bec->channel_id = $channel;
                    $bec->language_id = $language['id'];
                    $bec->commodity = $commodity;
                    $bec->save();
                }
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

    private function fdf($commodity)
    {
        switch ($commodity) {
            case 'electric':
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
                /V ([rate_info_electric_intro_term])
                /T (rate_info_electric_intro_term)
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
                /V ([rate_info_electric_rate_amount])
                /T (rate_info_electric_rate_amount)
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
                break;
            
            case 'gas':
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
                /V ([rate_info_gas_term])
                /T (rate_info_gas_term)
                >> 
                <<
                /V ()
                /T (signature_agent)
                >> 
                <<
                /V ([account_number_gas])
                /T (account_number_gas)
                >> 
                <<
                /V ([rate_info_gas_rate_amount])
                /T (rate_info_gas_rate_amount)
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
                /V ([rate_info_gas_intro_term])
                /T (rate_info_gas_intro_term)
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
                >>';
                break;
        }
    }
}
