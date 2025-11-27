<?php

use App\Models\BrandEztpvContract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IndraTieredContractRecordCorrections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // tiered electric
        $te_contracts = [
            'indra_pa_tiered_electric_english_20190611.pdf',
            'indra_pa_tiered_electric_spanish_20190612.pdf'
        ];
        $te = BrandEztpvContract::whereIn(
            'contract_pdf',
            $te_contracts
        )
        ->update([
            'contract_fdf' => $this->tiered_electric_fdf()
        ]);

        // tiered gas english
        $tge = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_tiered_gas_english_20190611.pdf'
        )
        ->update([
            'contract_fdf' => $this->tiered_gas_fdf(),
            'signature_info' => '{"6":{"0":"126,611,122,24"}}',
            'signature_info_customer' => '{"6":{"0":"126,611,122,24"}}',
            'signature_info_agent' => '{"6":{"0":"126,635,122,24"}}'
        ]);

        // tiered gas spanish
        $tgs = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_tiered_gas_spanish_20190611.pdf'
        )
        ->update([
            'contract_fdf' => $this->tiered_gas_fdf(),
            'signature_info' => '{"6":{"0":"118,500,122,24"}}',
            'signature_info_customer' => '{"6":{"0":"118,500,122,24"}}',
            'signature_info_agent' => '{"6":{"0":"118,526,122,24"}}'
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

    private function tiered_electric_fdf()
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
        /V ([rate_info_electric_intro_term])
        /T (rate_info_electric_intro_term)
        >>
        <<
        /V ()
        /T (signature_agent)
        >>
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
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
        /V ([rate_info_electric_calculated_rate_amount])
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
    }

    private function tiered_gas_fdf()
    {
        return '<<
        /V ([address_service])
        /T (address_service)
        >>
        <<
        /V ([green_percentage_formatted])
        /T (green_percentage_formatted)
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
        /V ([utility_name_all])
        /T (utility_name_all)
        >>
        <<
        /V ([city_state_zip_service])
        /T (city_state_zip_service)
        >>';
    }
}
