<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateKiwiNyRetailEnglishElecttricTieredVariableProductSpecificContract extends Migration
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
        /V ()
        /T (auth_fullname)
        >> 
        <<
        /V ()
        /T (date)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ()
        /T (auth_fullname_fl)
        >>';
        $bec->signature_info = '{"8":{"0":"137,478,180,44"}}';
        $bec->signature_info_customer = '{"8":{"0":"137,478,180,44"}}';
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
