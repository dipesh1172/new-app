<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeSignatureInfoOnFteDemoContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = BrandEztpvContract::where('brand_id', '04B0F894-172C-470F-813B-4F58DBD35BAE')
            ->where('contract_pdf', 'FTE_Demo_Contract_2019_02_06.pdf')
            ->update([
                'signature_info' => '{"1":{"0":"262,632,141,30"}}'
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $contracts = BrandEztpvContract::where('brand_id', '04B0F894-172C-470F-813B-4F58DBD35BAE')
            ->where('contract_pdf', 'FTE_Demo_Contract_2019_02_06.pdf')
            ->update([
                'signature_info' => '{"1":{"0":"262,642,141,20"}}'
            ]);
    }
}
