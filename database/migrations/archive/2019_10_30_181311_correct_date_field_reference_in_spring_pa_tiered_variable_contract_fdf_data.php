<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CorrectDateFieldReferenceInSpringPaTieredVariableContractFdfData extends Migration
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
            'spring_pa%tiered-variable%'
        )
            ->update([
                'contract_fdf' => '<<
            /V ([auth_fullname])
            /T (auth_fullname)
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
            /V ([date])
            /T (date_af_date)
            >> 
            <<
            /V ([auth_fullname_fl])
            /T (auth_fullname_fl)
            >> 
            <<
            /V ([rate_info_electric_calculated_intro_rate_amount])
            /T (rate_info_electric_calculated_intro_rate_amount)
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
