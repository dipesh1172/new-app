<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePageCountOnRpaOhDualEnglishContract extends Migration
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
            'rpa_oh_dtd_residential_dual_20190930.pdf'
        )
            ->update([
                'number_of_pages' => 8
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $bec = BrandEztpvContract::where(
            'contract_pdf',
            'rpa_oh_dtd_residential_dual_20190930.pdf'
        )
            ->update([
                'number_of_pages' => 2
            ]);
    }
}
