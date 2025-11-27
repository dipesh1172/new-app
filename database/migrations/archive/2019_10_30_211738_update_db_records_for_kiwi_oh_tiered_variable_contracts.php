<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateDbRecordsForKiwiOhTieredVariableContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'kiwi_oh_dtd_residential_variable_dual_english_20190806.pdf' => [
                'contract_pdf' => 'kiwi_oh_dtd_residential_variable_dual_english_20191030.pdf',
                'contract_fdf' => $this->fdf()
            ],
            'kiwi_oh_dtd_residential_variable_dual_spanish_20190806.pdf' => [
                'contract_pdf' => 'kiwi_oh_dtd_residential_variable_dual_spanish_20191030.pdf',
                'contract_fdf' => $this->fdf()
            ],
            'kiwi_oh_dtd_residential_variable_electric_english_20190806.pdf' => [
                'contract_pdf' => 'kiwi_oh_dtd_residential_variable_electric_english_20191030.pdf',
                'contract_fdf' => $this->fdf()
            ],
            'kiwi_oh_dtd_residential_variable_electric_spanish_20190806.pdf' => [
                'contract_pdf' => 'kiwi_oh_dtd_residential_variable_electric_spanish_20191030.pdf',
                'contract_fdf' => $this->fdf()
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $becs = BrandEztpvContract::where(
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

    private function fdf()
    {
        return '<<
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
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_calculated_intro_rate_amount)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
        >> 
        <<
        /V ([utility_account_number_all])
        /T (utility_account_number_all)
        >>';
    }
}
