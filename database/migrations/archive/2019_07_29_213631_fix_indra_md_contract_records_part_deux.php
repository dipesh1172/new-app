<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class FixIndraMdContractRecordsPartDeux extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $round1 = BrandEztpvContract::where(
            'contract_pdf',
            'indra_md_fixed_electric_spanish_20190726.pdf'
        )
        ->update([
            'language_id' => 2
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
