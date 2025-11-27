<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRecordsForIndraMaTieredSigpageContracts extends Migration
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
            'indra_ma%tiered%sigpage%'
        )
            ->update([
                'signature_required' => 0,
                'signature_required_customer' => 0,
                'signature_required_agent' => 0,
                'contract_fdf' => $this->fdf()
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

    private function fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >>';
    }
}
