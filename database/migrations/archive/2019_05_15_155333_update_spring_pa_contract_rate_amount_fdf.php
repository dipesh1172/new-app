<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSpringPaContractRateAmountFdf extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $names = [
            'Spring Power and Gas',
            'Spring Power & Gas'
        ];

        $bec = BrandEztpvContract::select('brand_eztpv_contracts.id', 'brand_eztpv_contracts.commodity')
            ->join(
                'brands',
                'brand_eztpv_contracts.brand_id',
                'brands.id'
            )
            ->whereIn('brands.name', $names)
            ->get();

        foreach ($bec as $contract) {

            $update = BrandEztpvContract::find($contract->id);

            switch ($contract->commodity) {
                case 'dual':
                    $update->contract_fdf = $this->dual_fdf();
                    break;
                
                case 'gas':
                    $update->contract_fdf = $this->gas_fdf();
                    break;
                
                case 'electric':
                    $update->contract_fdf = $this->electric_fdf();
                    break;
            }
            $update->save();
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
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_gas_calculated_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
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
        /V ([rate_info_electric_calculated_rate_amount])
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
        /V ([rate_info_gas_calculated_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([date])
        /T (date_af_date)
        >>';
    }
}
