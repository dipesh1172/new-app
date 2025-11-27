<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateLinkFdfData extends Migration
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
            ->where('channel_id', 1)
            ->update([
                'contract_fdf' => $this->new_dtd_fdf()
            ]);

        $documents = BrandEztpvContract::where('brand_id', '776c7324-32f9-4163-b6ed-f1d13df92aca')
            ->where('document_type_id', 1)
            ->where('state_id', 52)
            ->where('channel_id', 3)
            ->update([
                'contract_fdf' => $this->new_retail_fdf()
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

    public function new_dtd_fdf()
    {
        return '<<
        /V ()
        /T (pod_id)
        >> 
        <<
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
        /V ([date])
        /T (date)
        >> 
        <<
        /V ()
        /T (customer_signature2)
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
        /V /
        /T (payment_options_email_pad_form)
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
        /V ()
        /T (customer_signature)
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
        /V ([initials])
        /T (initials)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_rate_term)
        >> 
        <<
        /V ([city_billing])
        /T (billing_city)
        >> 
        <<
        /V ([billing_invoice_email])
        /T (billing_invoice_email)
        >> 
        <<
        /V /
        /T (payment_options_post_pay)
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
        /V ()
        /T (pad_transit_number)
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
        /V ()
        /T (pad_bank_name)
        >> 
        <<
        /V ([gas_variable_rate_checkbox])
        /T (rate_info_gas_rate_type_variable)
        >> 
        <<
        /V ()
        /T (pad_account_number)
        >> 
        <<
        /V ()
        /T (pad_account_holder_name)
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
        /V ()
        /T (pad_institution_number)
        >> 
        <<
        /V ([date])
        /T (date2)
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

    public function new_retail_fdf()
    {
        return '<<
        /V ()
        /T (pod_id)
        >> 
        <<
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
        /V ([date])
        /T (date)
        >> 
        <<
        /V ()
        /T (customer_signature2)
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
        /V /
        /T (payment_options_email_pad_form)
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
        /V ()
        /T (customer_signature)
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
        /V ([initials])
        /T (initials)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_rate_term)
        >> 
        <<
        /V ([city_billing])
        /T (billing_city)
        >> 
        <<
        /V ([billing_invoice_email])
        /T (billing_invoice_email)
        >> 
        <<
        /V /
        /T (payment_options_post_pay)
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
        /V ()
        /T (pad_transit_number)
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
        /V ()
        /T (pad_bank_name)
        >> 
        <<
        /V ([gas_variable_rate_checkbox])
        /T (rate_info_gas_rate_type_variable)
        >> 
        <<
        /V ()
        /T (pad_account_number)
        >> 
        <<
        /V ()
        /T (pad_account_holder_name)
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
        /V ()
        /T (pad_institution_number)
        >> 
        <<
        /V ([date])
        /T (date2)
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
