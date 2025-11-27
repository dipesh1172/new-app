<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\ServiceType;
use App\Models\ProviderIntegration;
use App\Models\Brand;

class AddIndraApiProviderIntegrations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $servicetype = ServiceType::where('name', 'Indra Active/DNC API')->first();
        $brand = Brand::where('name', 'like', 'Indra%')->whereNotNull('client_id')->first();
        $prod = new ProviderIntegration();
        $prod->brand_id = $brand->id;
        $prod->env_id = 1;
        $prod->service_type_id = $servicetype->id;
        $prod->provider_integration_type_id = 2;
        $prod->password = 'BE4CAF30-2DFE-4FF6-A147-D56056DD1003';
        $prod->hostname = 'https://api.columbiautilities.com/api/supressionlist/';
        $prod->save();

        $staging = new ProviderIntegration();
        $staging->brand_id = '4e65aab8-4dae-48ef-98ee-dd97e16cbce6';
        $staging->provider_integration_type_id = 2;
        $staging->service_type_id = 22;
        $staging->password = 'BE4CAF30-2DFE-4FF6-A147-D56056DD1003';
        $staging->hostname = 'https://apidev.columbiautilities.com/api/supressionlist/';
        $staging->env_id = 2;
        $staging->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
