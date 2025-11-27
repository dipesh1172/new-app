<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRpaIlContractFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_il_res_dtd_dual_english_custom_20191017.pdf' => 'rpa_il_res_dtd_dual_english_custom_20191111.pdf',
            'rpa_il_res_dtd_dual_spanish_custom_20191017.pdf' => 'rpa_il_res_dtd_dual_spanish_custom_20191111.pdf'
        ];

        foreach ($contracts as $old => $new) {
            $bec = BrandEztpvContract::where(
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
