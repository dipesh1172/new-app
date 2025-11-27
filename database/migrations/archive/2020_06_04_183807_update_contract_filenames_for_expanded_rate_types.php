<?php

use App\Models\BrandEztpvContract;
use Illuminate\Database\Migrations\Migration;

class UpdateContractFilenamesForExpandedRateTypes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $becs = BrandEztpvContract::select(
            'id',
            'contract_pdf'
        )
        ->where(
            'rate_type_id',
            3
        )
        ->get();

        foreach ($becs as $bec) {
            $replace = null;
            if (
                strpos($bec->contract_pdf, 'fixed-tiered')
                || strpos($bec->contract_pdf, 'tiered-variable')
            ) {
                continue;
            } elseif (strpos($bec->contract_pdf, 'tiered')) {
                $replace = str_replace('tiered', 'fixed-tiered', $bec->contract_pdf);
            } elseif (strpos($bec->contract_pdf, 'variable')) {
                $replace = str_replace('variable', 'tiered-variable', $bec->contract_pdf);
            }

            if (isset($replace)) {
                $update = BrandEztpvContract::where(
                    'id',
                    $bec->id
                )
                ->update([
                    'contract_pdf' => $replace,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // unnecessary
    }
}
