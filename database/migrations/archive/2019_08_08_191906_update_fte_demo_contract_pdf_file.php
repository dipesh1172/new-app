<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateFteDemoContractPdfFile extends Migration
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
            'FTE_Demo_Contract_2019_07_26.pdf'
        )
        ->update([
            'contract_pdf' => 'FTE_Demo_Contract_2019_02_06.pdf'
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
