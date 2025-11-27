<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateLinkTmContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $documents = BrandEztpvContract::where('brand_id', '776c7324-32f9-4163-b6ed-f1d13df92aca')
            ->where('document_type_id', 1)
            ->where('state_id', 52)
            ->where('channel_id', 2)
            ->update([
                'contract_pdf' => 'Link_TM_dual_2019_02_05.pdf',
                'contract_fdf' => $this->new_fdf()
            ]);

        $documents = BrandEztpvContract::where('brand_id', '776c7324-32f9-4163-b6ed-f1d13df92aca')
            ->where('document_type_id', 2)
            ->where('state_id', 52)
            ->where('channel_id', 2)
            ->update([
                'contract_pdf' => 'Link_TM_site_schedule_2019_02_05.pdf'
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }

    public function new_fdf()
    {
        return '<<
        /V ([state_service])
        /T (service_province)
        >> 
        <<
        /V ([start_date_gas])
        /T (service_flow_date_gas)
        >> 
        <<
        /V ([rate_info_cancellation_fee])
        /T (etf)
        >> 
        <<
        /V ([account_number_gas])
        /T (site_id_gas)
        >> 
        <<
        /V ([service_invoice_email])
        /T (service_invoice_email)
        >> 
        <<
        /V ([billing_invoice_paper])
        /T (billing_invoice_paper)
        >> 
        <<
        /V ([phone_number])
        /T (phone_number)
        >> 
        <<
        /V ([city_service])
        /T (service_city)
        >> 
        <<
        /V ([ah_date_of_birth])
        /T (customer_dob)
        >> 
        <<
        /V /
        /T (payment_options_pad)
        >> 
        <<
        /V ([account_number_electric])
        /T (site_id_electric)
        >> 
        <<
        /V ([agent_id] / [confirmation_code])
        /T (agent_id_slash_confirmation_code)
        >> 
        <<
        /V ([zip_service])
        /T (service_postal_code)
        >> 
        <<
        /V ([address_billing])
        /T (billing_address)
        >> 
        <<
        /V ([rate_info_gas_term])
        /T (rate_info_gas_rate_term)
        >> 
        <<
        /V ([bill_fullname])
        /T (bill_fullname)
        >> 
        <<
        /V ([service_invoice_paper])
        /T (service_invoice_paper)
        >> 
        <<
        /V ()
        /T (secondary_phone_number)
        >> 
        <<
        /V ([electric_fixed_rate_checkbox])
        /T (rate_info_electric_rate_type_fixed)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([rate_info_gas_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_rate_term)
        >> 
        <<
        /V /
        /T (payment_options_post_pay)
        >> 
        <<
        /V ([billing_invoice_email])
        /T (billing_invoice_email)
        >> 
        <<
        /V ([city_billing])
        /T (billing_city)
        >> 
        <<
        /V ([email_address])
        /T (email_address)
        >> 
        <<
        /V ([start_date_electric])
        /T (service_flow_date_electric)
        >> 
        <<
        /V /
        /T (payment_options_pacc)
        >> 
        <<
        /V ([zip_billing])
        /T (billing_postal_code)
        >> 
        <<
        /V ([gas_variable_rate_checkbox])
        /T (rate_info_gas_rate_type_variable)
        >> 
        <<
        /V ([address_service])
        /T (service_address)
        >> 
        <<
        /V ([state_billing])
        /T (billing_province)
        >> 
        <<
        /V ([gas_fixed_rate_checkbox])
        /T (rate_info_gas_rate_type_fixed)
        >> 
        <<
        /V ([electric_variable_rate_checkbox])
        /T (rate_info_electric_rate_type_variable)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >>';
    }
}
