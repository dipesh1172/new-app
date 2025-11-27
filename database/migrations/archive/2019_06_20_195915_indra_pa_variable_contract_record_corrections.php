<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaVariableContractRecordCorrections extends Migration
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

        $duals = BrandEztpvContract::where(
            'brand_id',
            $brand->id
        )
        ->where(
            'contract_pdf',
            'LIKE',
            'indra_pa_variable_dual%'
        )
        ->update([
            'contract_fdf' => $this->dual_fdf()
        ]);

        $electrics = BrandEztpvContract::where(
            'brand_id',
            $brand->id
        )
        ->where(
            'contract_pdf',
            'LIKE',
            'indra_pa_variable_electric%'
        )
        ->update([
            'contract_fdf' => $this->electric_fdf()
        ]);

        $gasses = BrandEztpvContract::where(
            'brand_id',
            $brand->id
        )
        ->where(
            'contract_pdf',
            'LIKE',
            'indra_pa_variable_gas%'
        )
        ->update([
            'contract_fdf' => $this->gas_fdf()
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

    private function dual_fdf()
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
        /V ([utility_name_all])
        /T (utility_name_all)
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

    private function electric_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([text_computed_electric_cancellation_fee_short])
        /T (text_computed_electric_cancellation_fee_short)
        >> 
        <<
        /V ([address_service])
        /T (address_service)
        >> 
        <<
        /V ([city_state_zip_service])
        /T (city_state_zip_service)
        >> 
        <<
        /V ([computed_multiline_auth_fullname_fl_plus_service_address])
        /T (computed_multiline_auth_fullname_fl_plus_service_address)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
        >> 
        <<
        /V ([green_percentage_formatted])
        /T (green_percentage_formatted)
        >> 
        <<
        /V ()
        /T (signature_agent)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number_electric)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name_all)
        >>';
    }

    private function gas_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([text_computed_gas_cancellation_fee_short])
        /T (text_computed_gas_cancellation_fee_short)
        >> 
        <<
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_calculated_intro_rate_amount)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([address_service])
        /T (address_service)
        >> 
        <<
        /V ([account_number_gas])
        /T (account_number_gas)
        >> 
        <<
        /V ([city_state_zip_service])
        /T (city_state_zip_service)
        >> 
        <<
        /V ([computed_multiline_auth_fullname_fl_plus_service_address])
        /T (computed_multiline_auth_fullname_fl_plus_service_address)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([green_percentage_formatted])
        /T (green_percentage_formatted)
        >> 
        <<
        /V ()
        /T (signature_agent)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name_all)
        >>';
    }
}
