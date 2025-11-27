<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraMaContractRateAmountDisplay extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $fixed = BrandEztpvContract::where(
                    'contract_pdf',
                    'LIKE',
                    'indra%fixed%'
                )
            ->update([
                'contract_fdf' => $this->fixed_fdf()
            ]);

        $fixed = BrandEztpvContract::where(
                'contract_pdf',
                'LIKE',
                'indra%tiered%'
            )
        ->update([
            'contract_fdf' => $this->tiered_fdf()
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

    private function fixed_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([text_computed_electric_cancellation_fee_short])
        /T (text_computed_electric_cancellation_fee_short)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage_2)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([computed_multiline_auth_fullname_fl_plus_service_address])
        /T (computed_multiline_auth_fullname_fl_plus_service_address)
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

    private function tiered_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([text_computed_electric_cancellation_fee_short])
        /T (text_computed_electric_cancellation_fee_short)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage_2)
        >> 
        <<
        /V ([rate_info_electric_tiered_rate_amount])
        /T (rate_info_electric_tiered_rate_amount)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage)
        >> 
        <<
        /V ([computed_multiline_auth_fullname_fl_plus_service_address])
        /T (computed_multiline_auth_fullname_fl_plus_service_address)
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
        /V ([utility_name_all])
        /T (utility_name_all)
        >>';
    }
}
