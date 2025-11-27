<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateFdfDataForKiwiNyFixedContracts extends Migration
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
            'kiwi_ny_dtd_residential_fixed%'
        )
            ->update([
                'contract_fdf' => '<<
            /V ([auth_fullname])
            /T (auth_fullname)
            >> 
            <<
            /V ([date])
            /T (date)
            >> 
            <<
            /V ()
            /T (signature_customer)
            >> 
            <<
            /V ([rate_info_electric_calculated_rate_amount])
            /T (rate_info_electric_calculated_rate_amount)
            >> 
            <<
            /V ([auth_fullname_fl])
            /T (auth_fullname_fl)
            >> 
            <<
            /V ([rate_info_electric_term])
            /T (rate_info_electric_term)
            >> 
            <<
            /V ([rate_info_electric_term_uom])
            /T (rate_info_electric_term_uom)
            >>'
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
}
