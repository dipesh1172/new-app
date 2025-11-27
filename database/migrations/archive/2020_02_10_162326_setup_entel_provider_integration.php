<?php

use App\Models\Brand;
use App\Models\ServiceType;
use Ramsey\Uuid\Uuid;
use App\Models\ProviderIntegration;
use Illuminate\Database\Migrations\Migration;

class SetupEntelProviderIntegration extends Migration
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
            'Entel Marketing LLC'
        )->first();

        $st = new ServiceType();
        $st->name = 'Entel FTP Site';
        $st->save();

        $pi = new ProviderIntegration();
        $pi->id = Uuid::uuid4();
        $pi->brand_id = $brand->id;
        $pi->service_type_id = $st->id;
        $pi->provider_integration_type_id = 2;
        $pi->username = 'EntelUser3700';
        $pi->password = 'Entel012020!!t';
        $pi->hostname = 'ftp.entelenergybroker.com';
        $pi->notes = '';
        $pi->env_id = 1;
        $pi->save();

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
        $st = ServiceType::where('name', 'Entel FTP Site')->first()->delete();

        $brand = Brand::where(
            'name',
            'Entel Marketing LLC'
        )->first();

        $pi = ProviderIntegration::where('brand_id', $brand->id)->first()->delete();
    }
}
