<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\ServiceType;
use App\Models\ProviderIntegration;
use App\Models\Brand;

class AddGenieCustomerEligibilityApiPi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $brands = [
            'IDT Energy',
            'Residents Energy',
            'Citizens Choice Energy',
            'Town Square'
        ];

        DB::table('service_types')->insert([
            [
                'created_at' => now(),
                'updated_at' => now(),
                'name' => 'Genie Customer Eligibility Check API'
            ]
        ]);

        $servicetype = ServiceType::where('name', 'Genie Customer Eligibility Check API')->first();
        foreach ($brands as $brandName) {

            $brand = Brand::where('name', $brandName)->whereNotNull('client_id')->first();
            if ($brand) {
                $prod = new ProviderIntegration();
                $prod->brand_id = $brand->id;
                $prod->env_id = 1;
                $prod->service_type_id = $servicetype->id;
                $prod->provider_integration_type_id = 2;
                $prod->username = 'DxcVmsApi';
                $prod->password = 'zBo1G5Gf4n5';
                $prod->hostname = 'https://vms.genieretail.com/api/';
                $prod->save();

                $staging = new ProviderIntegration();
                $staging->brand_id = $brand->id;
                $staging->provider_integration_type_id = 2;
                $staging->service_type_id = $servicetype->id;
                $staging->username = 'DxcVmsApi';
                $staging->password = 'zBo1G5Gf4n5';
                $staging->hostname = 'https://vms.genieretail.com/api/';
                $staging->env_id = 2;
                $staging->save();
            }
        }
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
