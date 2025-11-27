<?php

use App\Models\Brand;
use App\Models\ServiceType;
use Ramsey\Uuid\Uuid;
use App\Models\ProviderIntegration;
use Illuminate\Database\Migrations\Migration;

class SetupInspireRatesApi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $brand = Brand::where(
            'name',
            'Inspire Energy'
        )->first();

        $st = new ServiceType();
        $st->name = 'Inspire REST API';
        $st->save();

        $pi = new ProviderIntegration();
        $pi->id = Uuid::uuid4();
        $pi->brand_id = $brand->id;
        $pi->service_type_id = $st->id;
        $pi->provider_integration_type_id = 2;
        $pi->username = 'DXC3C269';
        $pi->password = 'B02PUI21';
        $pi->hostname = 'https://prod-garcon.herokuapp.com/api/1/clients/DXC3C269/offers/RESI/D2D';
        $pi->notes = '';
        $pi->env_id = 1;
        $pi->save();

        $pi = new ProviderIntegration();
        $pi->id = Uuid::uuid4();
        $pi->brand_id = $brand->id;
        $pi->service_type_id = $st->id;
        $pi->provider_integration_type_id = 2;
        $pi->username = 'DXC3C269';
        $pi->password = 'e1ce2b6bcfc2197052a3c74e2f207e3e';
        $pi->hostname = 'https://stg-garcon.herokuapp.com/api/1/clients/DXC3C269/offers/RESI/D2D';
        $pi->notes = '';
        $pi->env_id = 2;
        $pi->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        try {
            $st = ServiceType::where(
                'name',
                'Inspire REST API'
            )->first();
            if ($st) {
                $st->delete();
            }
        } catch (Exception $e) {
            info("Error removing ServiceType: " . $e);
        }

        try {
            $deletedRows = ProviderIntegration::where('username', 'DXC3C269')->delete();
        } catch (Exception $e) {
            info("Error removing ProviderIntegrations: " . $e);
        }
    }
}
