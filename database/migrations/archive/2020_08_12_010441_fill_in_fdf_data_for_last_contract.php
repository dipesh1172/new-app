<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class FillInFdfDataForLastContract extends Migration
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
            'kiwi_energy_NY_Residential_Retail_electric_tiered-variable_english_custom_1597178699.pdf'
        )
        ->first();

        $bec->contract_fdf = '<<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([signature_customer])
        /T (signature_customer)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >>';
        $bec->save();
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
