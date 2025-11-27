<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateMedianOhContractSignaturePositionMk2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = BrandEztpvContract::where('contract_pdf', 'Median_OH_DTD_any_Promotions_20190325.pdf')
            ->update([
                'signature_info' => '{"1":{"0":"345,212,248,16"}}'
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $bec = BrandEztpvContract::where('contract_pdf', 'Median_OH_DTD_any_Promotions_20190325.pdf')
            ->update([
                'signature_info' => '{"1":{"0":"345,284,248,16"}}'
            ]);
    }
}
