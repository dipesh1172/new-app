<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class GenerateRecordsForRpaIlSpanishContract extends Migration
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
            'RPA Energy%'
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

        $states = [
            0 => [
                'state_id' => 14,
                'state_abbrev' => 'IL',
                'contract_pdf' => 'rpa_il_dtd_residential_spanish_dual_contract_20190702.pdf',
                'contract_fdf' => $this->fdf(),
                'signature_info_customer' => '{"1":{"0":"27,663,144,29"}}',
                'signature_info_agent' => '{"1":{"0":"27,697,144,29"}}'
            ],
            1 => [
                'state_id' => 31,
                'state_abbrev' => 'NJ',
                'contract_pdf' => 'rpa_nj_dtd_residential_spanish_dual_contract_20190702.pdf',
                'contract_fdf' => $this->fdf(),
                'signature_info_customer' => '{"1":{"0":"27,610,127,29"}}',
                'signature_info_agent' => '{"1":{"0":"27,649,127,29"}}'
            ],
            2 => [
                'state_id' => 39,
                'state_abbrev' => 'PA',
                'contract_pdf' => 'rpa_pa_dtd_residential_spanish_dual_contract_20190702.pdf',
                'contract_fdf' => $this->pa_fdf(),
                'signature_info_customer' => '{"1":{"0":"27,683,127,29"}}',
                'signature_info_agent' => '{"1":{"0":"27,722,127,29"}}'
            ]
        ];

        foreach ($states as $state) {
            foreach ($channels as $channel) {
                foreach ($commodities as $commodity) {
                    $bec = new BrandEztpvContract;
                    $bec->brand_id = $brand->id;
                    $bec->document_type_id = 1;
                    $bec->contract_pdf = $state['contract_pdf'];
                    $bec->contract_fdf = $state['contract_fdf'];
                    $bec->page_size = 'Letter';
                    $bec->number_of_pages = 2;
                    $bec->signature_required = 1;
                    $bec->signature_required_customer = 1;
                    $bec->signature_info = $state['signature_info_customer'];
                    $bec->signature_info_customer = $state['signature_info_customer'];
                    $bec->signature_required_agent = 1;
                    $bec->signature_info_agent = $state['signature_info_agent'];
                    $bec->state_id = $state['state_id'];
                    $bec->channel_id = $channel;
                    $bec->language_id = 2;
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

    private function fdf()
    {
        return '<<
        /V /Yes
        /T (single_bill)
        >> 
        <<
        /V ([address_service])
        /T (address_service)
        >> 
        <<
        /V ([utility_electric_name])
        /T (utility_electric_name)
        >> 
        <<
        /V ([confirmation_code])
        /T (confirmation_code)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([agent_fullname])
        /T (agent_fullname)
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
        /V ([account_number_electric])
        /T (account_number_electric)
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
        /V ([utility_gas_name])
        /T (utility_gas_name)
        >> 
        <<
        /V ([phone_number])
        /T (phone_number)
        >> 
        <<
        /V ([bill_fullname])
        /T (bill_fullname)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([city_state_zip_service])
        /T (city_state_zip_service)
        >> 
        <<
        /V ([email_address])
        /T (email_address)
        >> 
        <<
        /V ([agent_id])
        /T (agent_id)
        >>';
    }

    private function pa_fdf()
    {
        return '<<
        /V /Yes
        /T (single_bill)
        >> 
        <<
        /V ([address_service])
        /T (address_service)
        >> 
        <<
        /V ([utility_electric_name])
        /T (utility_electric_name)
        >> 
        <<
        /V ([confirmation_code])
        /T (confirmation_code)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([agent_fullname])
        /T (agent_fullname)
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
        /V ([account_number_electric])
        /T (account_number_electric)
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
        /V ([initials])
        /T (initials)
        >> 
        <<
        /V ([utility_gas_name])
        /T (utility_gas_name)
        >> 
        <<
        /V ([phone_number])
        /T (phone_number)
        >> 
        <<
        /V ([bill_fullname])
        /T (bill_fullname)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([city_state_zip_service])
        /T (city_state_zip_service)
        >> 
        <<
        /V ([email_address])
        /T (email_address)
        >> 
        <<
        /V ([agent_id])
        /T (agent_id)
        >>';
    }
}
