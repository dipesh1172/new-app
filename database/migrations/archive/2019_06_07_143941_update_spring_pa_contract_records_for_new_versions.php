<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateSpringPaContractRecordsForNewVersions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec1 = BrandEztpvContract::where(
            'contract_pdf',
            'spring_pa_res_dual_20190428.pdf'
            )
            ->update([
                'contract_pdf' => 'spring_pa_res_dual_20190607.pdf'
            ]);
        
        $bec2 = BrandEztpvContract::where(
            'contract_pdf',
            'spring_pa_res_dual_span_20190428.pdf'
            )
            ->update([
                'contract_pdf' => 'spring_pa_res_dual_span_20190607.pdf'
            ]);
        
        $bec3 = BrandEztpvContract::where(
            'contract_pdf',
            'spring_pa_res_electric_20190428.pdf'
            )
            ->update([
                'contract_pdf' => 'spring_pa_res_electric_20190607.pdf'
            ]);
        
        $bec4 = BrandEztpvContract::where(
            'contract_pdf',
            'spring_pa_res_electric_span_20190428.pdf'
            )
            ->update([
                'contract_pdf' => 'spring_pa_res_electric_span_20190607.pdf'
            ]);
        
        $bec5 = BrandEztpvContract::where(
            'contract_pdf',
            'spring_pa_res_gas_20190428.pdf'
            )
            ->update([
                'contract_pdf' => 'spring_pa_res_gas_20190607.pdf'
            ]);
        
        $bec6 = BrandEztpvContract::where(
            'contract_pdf',
            'spring_pa_res_gas_span_20190428.pdf'
            )
            ->update([
                'contract_pdf' => 'spring_pa_res_gas_span_20190607.pdf'
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
}
