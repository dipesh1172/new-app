<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateFdfDataForSpringPaTieredVariableContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'spring_pa_res_dual_tiered-variable_english_20191030.pdf' => [
                'contract_pdf' => 'spring_pa_res_dual_tiered-variable_english_20191030.pdf',
                'contract_fdf' => $this->dual_fdf()
            ],
            'spring_pa_res_dual_tiered-variable_spanish_20190826.pdf' => [
                'contract_pdf' => 'spring_pa_res_dual_tiered-variable_spanish_20191030.pdf',
                'contract_fdf' => $this->dual_fdf()
            ],
            'spring_pa_res_electric_tiered-variable_english_20191030.pdf' => [
                'contract_pdf' => 'spring_pa_res_electric_tiered-variable_english_20191030.pdf',
                'contract_fdf' => $this->dual_fdf()
            ],
            'spring_pa_res_electric_tiered-variable_spanish_20190826.pdf' => [
                'contract_pdf' => 'spring_pa_res_electric_tiered-variable_spanish_20191030.pdf',
                'contract_fdf' => $this->dual_fdf()
            ],
            'spring_pa_res_gas_tiered-variable_english_20191030.pdf' => [
                'contract_pdf' => 'spring_pa_res_gas_tiered-variable_english_20191030.pdf',
                'contract_fdf' => $this->dual_fdf()
            ],
            'spring_pa_res_gas_tiered-variable_spanish_20190805.pdf' => [
                'contract_pdf' => 'spring_pa_res_gas_tiered-variable_spanish_20191030.pdf',
                'contract_fdf' => $this->dual_fdf()
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
                ->update([
                    'contract_pdf' => $data['contract_pdf'],
                    'contract_fdf' => $data['contract_fdf']
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

    private function dual_fdf()
    {
        return '<<
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
        /V ([date_af_date])
        /T (date_af_date)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
        >>';
    }
}
