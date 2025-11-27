<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRecordsForKiwiNyFixedRetail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'kiwi_ny_dtd_residential_fixed_electric_english_20190906.pdf'
        )
            ->first();
        $new = $bec->replicate();
        $new->channel_id = 3;
        $new->save();
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
