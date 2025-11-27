<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateIndraIlContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'indra_il_res_allChannel_electric_tiered_english_sigpage_20200228.docx' => 'indra_il_res_allChannel_electric_tiered_english_sigpage_20200302.docx'
        ];

        foreach ($contracts as $old => $new) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
            ->update([
                'contract_pdf' => $new
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
}
