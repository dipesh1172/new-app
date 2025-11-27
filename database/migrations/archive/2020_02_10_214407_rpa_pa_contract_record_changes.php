<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaPaContractRecordChanges extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // update rate_type_id
        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'LIKE',
            'rpa_pa%'
        )
            ->whereNull(
                'rate_type_id'
            )
            ->update([
                'rate_type_id' => 2
            ]);

        // soft-delete old contract records
        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'rpa_pa_dtd_residential_dual_english_20191017.pdf'
        )
            ->update([
                'deleted_at' => NOW()
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
