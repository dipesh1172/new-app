<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SpringMdCustomContractCorrections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'spring_md_res_electric_english_20190820.pdf' => 'spring_md_res_electric_english_20190822.pdf',
            'spring_md_res_gas_spanish_20190820.pdf' => 'spring_md_res_gas_spanish_20190822.pdf',
            'spring_md_res_gas_english_20190820.pdf' => 'spring_md_res_gas_english_20190822.pdf',
            'spring_md_res_dual_english_20190820.pdf' => 'spring_md_res_dual_english_20190822.pdf',
            'spring_md_res_dual_spanish_20190820.pdf' => 'spring_md_res_dual_spanish_20190822.pdf',
            'spring_md_res_electric_spanish_20190820.pdf' => 'spring_md_res_electric_spanish_20190822.pdf'
        ];
        
        foreach ($contracts as $old => $new)
        $bec = BrandEztpvContract::where(
            'contract_pdf',
            $old
        )
        ->update([
            'contract_pdf' => $new,
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
        /V ([rate_info_electric_intro_rate_amount])
        /T (rate_info_electric_intro_rate_amount)
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
