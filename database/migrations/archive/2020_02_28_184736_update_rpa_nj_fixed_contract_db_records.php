<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRpaNjFixedContractDbRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_nj_allMarket_allChannel_dual_fixed_english_custom_20200227.docx' => 'rpa_nj_allMarket_allChannel_dual_fixed_english_custom_20200228.docx',
	        'rpa_nj_allMarket_allChannel_dual_fixed_spanish_custom_20200227.docx' => 'rpa_nj_allMarket_allChannel_dual_fixed_spanish_custom_20200228.docx'
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
