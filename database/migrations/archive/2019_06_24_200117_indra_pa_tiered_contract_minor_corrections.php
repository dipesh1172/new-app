<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaTieredContractMinorCorrections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'LIKE',
            'indra_pa_tiered_dual%'
        )
        ->update([
            'contract_fdf' => $this->fdf()
        ]);
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
        /V ([rate_info_electric_intro_term])
        /T (rate_info_electric_intro_term)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
        /T (rate_info_electric_calculated_rate_amount)
        >> 
        <<
        /V ([rate_info_gas_term])
        /T (rate_info_gas_term)
        >> 
        <<
        /V ([rate_info_gas_calculated_rate_amount])
        /T (rate_info_gas_calculated_rate_amount)
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
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_calculated_intro_rate_amount)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
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
        /V ([rate_info_gas_intro_term])
        /T (rate_info_gas_intro_term)
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
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
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
