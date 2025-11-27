<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaVariableContracts extends Migration
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
                    'pdf' => 'indra_pa_variable_electric_english_20190613.pdf',
                    'sig_cust' => '{"6":{"0":"115,688,119,22"}}',
                    'sig_agent' => '{"6":{"0":"115,714,119,22"}}'
                ],
                'gas' => [
                    'pdf' => 'indra_pa_variable_gas_english_20190613.pdf',
                    'sig_cust' => '{"6":{"0":"120,616,122,22"}}',
                    'sig_agent' => '{"6":{"0":"120,642,122,22"}}'
                ]
                
            ],
            'spanish' => [
                'id' => 2,
                'electric' => [
                    'pdf' => 'indra_pa_variable_electric_spanish_20190613.pdf',
                    'sig_cust' => '{"6":{"0":"124,726,110,22"}}',
                    'sig_agent' => '{"6":{"0":"124,752,110,22"}}'
                ],
                'gas' => [
                    'pdf' => 'indra_pa_variable_gas_spanish_20190613.pdf',
                    'sig_cust' => '{"6":{"0":"121,627,122,22"}}',
                    'sig_agent' => '{"6":{"0":"121,653,122,22"}}'
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
                    $bec->rate_type_id = 2;
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
                /V ()
                /T (signature_agent)
                >> 
                <<
                /V ([account_number_gas])
                /T (account_number_gas)
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
                /V ([rate_info_gas_intro_rate_amount])
                /T (rate_info_gas_intro_rate_amount)
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
                /V ([city_state_zip_service])
                /T (city_state_zip_service)
                >>';
                break;
        }
    }
}
