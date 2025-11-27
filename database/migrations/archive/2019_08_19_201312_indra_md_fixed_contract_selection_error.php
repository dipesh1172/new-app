<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class IndraMdFixedContractSelectionError extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'LIKE',
            'indra_md_sigpage%'
        )
        ->get();

        foreach ($becs as $contract)
        {
            if (strstr($contract->contract_pdf, 'fixed')) {
                $rate_type_id = 1;
            } else {
                $rate_type_id = 3;
            }
            $bec = BrandEztpvContract::find($contract->id)
                ->update([
                    'rate_type_id' => $rate_type_id
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
