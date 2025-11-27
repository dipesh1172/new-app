<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateGapPaContractFdf extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = BrandEztpvContract::where('contract_pdf', '')
            ->update([
                'contract_fdf' => $this->pa_fdf()
            ]);
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

    private function pa_fdf()
    {
        return '<<
        /V ([date])
        /T (customer_signature_date)
        >> 
        <<
        /V ([computed_electric_other_fees])
        /T (computed_other_fees)
        >> 
        <<
        /V ([zip_service])
        /T (service_zip)
        >> 
        <<
        /V ([computed_electric_rate_type_plus_green])
        /T (computed_rate_type_plus_green)
        >> 
        <<
        /V ([state_service])
        /T (service_state)
        >> 
        <<
        /V ([state_billing])
        /T (billing_state)
        >> 
        <<
        /V ([city_service])
        /T (service_city)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([auth_relationship_authorized_agent])
        /T (signatory_authorized_agent)
        >> 
        <<
        /V ([company_name_electric])
        /T (company_name)
        >> 
        <<
        /V ([zip_billing])
        /T (billing_zip)
        >> 
        <<
        /V ([utility_electric_customer_service])
        /T (utility_electric_customer_service)
        >> 
        <<
        /V ([auth_fullname])
        /T (signatory_name)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number)
        >> 
        <<
        /V ([agent_fullname])
        /T (agent_name)
        >> 
        <<
        /V ([agent_id])
        /T (agent_id)
        >> 
        <<
        /V ([phone_number])
        /T (primary_phone_number)
        >> 
        <<
        /V ([auth_relationship_account_holder])
        /T (signatory_account_holder)
        >> 
        <<
        /V ([office_name])
        /T (office_name)
        >> 
        <<
        /V ([company_name_electric])
        /T (company_name_electric)
        >> 
        <<
        /V ([address_billing])
        /T (billing_address)
        >> 
        <<
        /V ()
        /T (customer_signature)
        >> 
        <<
        /V ([bill_fullname])
        /T (account_holder_name)
        >> 
        <<
        /V ([text_computed_electric_cancellation_fee])
        /T (cancellation_fee)
        >> 
        <<
        /V ([bill_fullname_same_as_auth_fullname])
        /T (signatory_name_same_as_account_holder_name)
        >> 
        <<
        /V ([city_billing])
        /T (billing_city)
        >> 
        <<
        /V ([date])
        /T (noc_transaction_date)
        >> 
        <<
        /V ([email_address])
        /T (email_address)
        >> 
        <<
        /V ([rate_info_electric_rate_amount_in_dollars])
        /T (rate_info_electric_rate_amount_in_dollars)
        >> 
        <<
        /V ([address_service])
        /T (service_address)
        >> 
        <<
        /V ([rate_info_electric_program_code])
        /T (rate_info_program_code)
        >> 
        <<
        /V ([service_address_same_as_billing_address])
        /T (service_address_same_as_billing_address)
        >>';
    }
}
