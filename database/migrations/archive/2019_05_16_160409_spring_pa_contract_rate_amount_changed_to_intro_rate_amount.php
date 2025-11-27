<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SpringPaContractRateAmountChangedToIntroRateAmount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $dual = BrandEztpvContract::where(
                    'contract_pdf',
                    'LIKE',
                    'spring%dual%'
                )
            ->update([
                'contract_fdf' => $this->dual_fdf()
            ]);

        $electric = BrandEztpvContract::where(
                    'contract_pdf',
                    'LIKE',
                    'spring%electric%'
                )
            ->update([
                'contract_fdf' => $this->electric_fdf()
            ]);

        $gas = BrandEztpvContract::where(
                    'contract_pdf',
                    'LIKE',
                    'spring%gas%'
                )
            ->update([
                'contract_fdf' => $this->gas_fdf()
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

    private function dual_fdf()
    {
        return '<<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([date])
        /T (date_af_date)
        >>';
    }

    private function electric_fdf()
    {
        return '<<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([date])
        /T (date_af_date)
        >>';
    }

    private function gas_fdf()
    {
        return '<<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([date])
        /T (date_af_date)
        >>';
    }
}
