<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaIlSpanishContractUpdateMigrationFix extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_il_dtd_residential_spanish_dual_contract_20190702.pdf' => [
                'contract_pdf' => 'rpa_il_res_dtd_dual_spanish_custom_20190913.pdf',
                'signature_info_customer' => '{"2":{"0":"32,411,130,30"}}',
                'signature_info_agent' => '{"2":{"0":"32,449,130,33"}}',
                'number_of_pages' => 6,
                'page_size' => 'Legal'
            ]
        ];

        foreach ($contracts as $old => $new) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
            ->update([
                'contract_pdf' => $new['contract_pdf'],
                'contract_fdf' => $this->fdf(),
                'signature_info' => $new['signature_info_customer'],
                'signature_info_customer' => $new['signature_info_customer'],
                'signature_info_agent' => $new['signature_info_agent'],
                'number_of_pages' => $new['number_of_pages'],
                'page_size' => $new['page_size']
            ]);
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
}
