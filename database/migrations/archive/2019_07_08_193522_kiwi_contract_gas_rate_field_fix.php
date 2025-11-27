<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class KiwiContractGasRateFieldFix extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'LIKE',
            'kiwi%'
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
        /V ([rate_info_gas_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_calculated_intro_rate_amount)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([commodity_type_all])
        /T (commodity_type_all)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([utility_account_number_all])
        /T (utility_account_number_all)
        >>';
    }
}
