<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRateTypeIdsToIndraMaContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $fixed = BrandEztpvContract::where(
                'contract_pdf',
                'LIKE',
                'indra%fixed%'
            )
            ->update([
                'rate_type_id' => 1
            ]);

        $tiered = BrandEztpvContract::where(
                'contract_pdf',
                'LIKE',
                'indra%tiered%'
            )
            ->update([
                'rate_type_id' => 3
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
