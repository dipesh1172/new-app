<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixSigDataForKiwiNyFixedContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $languages = [
            1 => '{"7":{"0":"152,328,180,44"}}',
            2 => '{"7":{"0":"136,560,180,44"}}'
        ];

        foreach ($languages as $lang => $sig) {
            $becs2 = BrandEztpvContract::where(
                'contract_pdf',
                'LIKE',
                'kiwi_ny_dtd_residential_fixed%'
            )
                ->where(
                    'language_id',
                    $lang
                )
                ->update([
                    'signature_info' => $sig,
                    'signature_info_customer' => $sig
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
