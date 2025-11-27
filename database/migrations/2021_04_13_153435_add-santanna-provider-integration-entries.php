<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\ProviderIntegration;

class AddSantannaProviderIntegrationEntries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $piProd = new ProviderIntegration();
        $piProd->brand_id = 'a6271008-2dc4-4bac-b6df-aa55d8b79ec7';
        $piProd->service_type_id = 99;
        $piProd->provider_integration_type_id = 2;
        $piProd->env_id = 1;
        $piProd->hostname = 'https://tmcheck.ses4energy.com/ws/AcctCheckZnAlpha.asmx?op=TPVPost ';
        $piProd->save();

        $piStag = new ProviderIntegration();
        $piStag->brand_id = '7c88b08c-5576-41f0-898a-1b1c8c8983c4';
        $piStag->service_type_id = 99;
        $piStag->provider_integration_type_id = 2;
        $piStag->env_id = 2;
        $piStag->hostname = 'https://tmcheck.ses4energy.com/ws/AcctCheckZnBeta.asmx?op=TPVPost ';
        $piStag->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ProviderIntegration::whereIn('brand_id', [
            'a6271008-2dc4-4bac-b6df-aa55d8b79ec7',
            '7c88b08c-5576-41f0-898a-1b1c8c8983c4'
        ])->where('service_type_id', 99)
            ->where('provider_integration_type_id', 2)
            ->delete();
    }
}
