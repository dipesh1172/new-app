<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixSpringMdFdfData extends Migration
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
            'spring_md%'
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
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_calculated_intro_rate_amount)
        >>';
    }
}
