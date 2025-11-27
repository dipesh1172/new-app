<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateFdfDataForIndraIlTieredContracts extends Migration
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
            'indra_il%sigpage%'
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
        /V ([rate_info_electric_intro_term])
        /T (rate_info_electric_intro_term)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_term_remaining])
        /T (rate_info_electric_term_remaining)
        >> 
        <<
        /V ([rate_info_electric_custom_data_1])
        /T (rate_info_electric_custom_data_1)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
        /T (rate_info_electric_calculated_rate_amount)
        >> 
        <<
        /V ([agent_id])
        /T (agent_id)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
        >>';
    }
}
