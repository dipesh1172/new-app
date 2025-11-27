<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateMedianOhContractPdfAgain extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $becs = BrandEztpvContract::withTrashed()
            ->where('contract_pdf', 'Median_OH_DTD_any_Promotions_20181009.pdf')
            ->update([
                'contract_pdf' => 'Median_OH_DTD_any_Promotions_20190325.pdf'
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $becs = BrandEztpvContract::withTrashed()
            ->where('contract_pdf', 'Median_OH_DTD_any_Promotions_20190325.pdf')
            ->update([
                    'contract_pdf' => 'Median_OH_DTD_any_Promotions_20181009.pdf'
                ]);
    }
}
