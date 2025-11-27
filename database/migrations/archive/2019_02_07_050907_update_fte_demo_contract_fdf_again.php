<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateFteDemoContractFdfAgain extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = BrandEztpvContract::where('brand_id', '04B0F894-172C-470F-813B-4F58DBD35BAE')
            ->where('contract_pdf', 'FTE_Demo_Contract_2019_02_06.pdf')
            ->update([
                'contract_fdf' => $this->new_fdf()
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $contracts = BrandEztpvContract::where('brand_id', '04B0F894-172C-470F-813B-4F58DBD35BAE')
            ->where('contract_pdf', 'FTE_Demo_Contract_2019_02_06.pdf')
            ->update([
                'contract_fdf' => $this->old_fdf()
            ]);
    }

    public function new_fdf()
    {
        return '<<
        /V ([state_service])
        /T (service_state)
        >> 
        <<
        /V ([city_service])
        /T (service_city)
        >> 
        <<
        /V ([rate_info_gas_term])
        /T (rate_info_gas_term)
        >> 
        <<
        /V ([agent_fullname])
        /T (agent_fullname)
        >> 
        <<
        /V ([account_number_gas])
        /T (account_number_gas)
        >> 
        <<
        /V ([address_service])
        /T (service_address)
        >> 
        <<
        /V ([rate_info_gas_rate_amount])
        /T (rate_info_gas_rate_amount)
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
        /V ([rate_info_electric_rate_amount_in_dollars])
        /T (rate_info_electric_rate_amount)
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
        /V ()
        /T (customer_signature)
        >> 
        <<
        /V ([date])
        /T (customer_signature_date)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([zip_service])
        /T (service_zip)
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

    public function old_fdf()
    {
        return '<<
        /V ([state_service])
        /T (service_state)
        >> 
        <<
        /V ([city_service])
        /T (service_city)
        >> 
        <<
        /V ([rate_info_gas_term])
        /T (rate_info_gas_term)
        >> 
        <<
        /V ([agent_fullname])
        /T (agent_fullname)
        >> 
        <<
        /V ([account_number_gas])
        /T (account_number_gas)
        >> 
        <<
        /V ([address_service])
        /T (service_address)
        >> 
        <<
        /V ([rate_info_gas_rate_amount_in_dollars])
        /T (rate_info_gas_rate_amount)
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
        /V ([rate_info_electric_rate_amount_in_dollars])
        /T (rate_info_electric_rate_amount)
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
        /V ()
        /T (customer_signature)
        >> 
        <<
        /V ([date])
        /T (customer_signature_date)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([zip_service])
        /T (service_zip)
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
