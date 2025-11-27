<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSpringPaContractRecordsForSignatures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'spring_pa_res_dual_20190607.pdf' => 'spring_pa_res_dual_english_20190805.pdf',
            'spring_pa_res_dual_span_20190607.pdf' => 'spring_pa_res_dual_spanish_20190805.pdf',
            'spring_pa_res_electric_20190607.pdf' => 'spring_pa_res_electric_english_20190805.pdf',
            'spring_pa_res_electric_span_20190607.pdf' => 'spring_pa_res_electric_spanish_20190805.pdf',
            'spring_pa_res_gas_20190607.pdf' => 'spring_pa_res_gas_english_20190805.pdf',
            'spring_pa_res_gas_span_20190607.pdf' => 'spring_pa_res_gas_spanish_20190805.pdf'
        ];
        foreach ($data as $old => $new)
        {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )->update([
                'contract_pdf' => $new,
                'contract_fdf' => $this->fdf()
            ]);
        }
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
        /V ([auth_fullname])
        /T (auth_fullname)
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
        /V ([date])
        /T (date_af_date)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >>';
    }
}
