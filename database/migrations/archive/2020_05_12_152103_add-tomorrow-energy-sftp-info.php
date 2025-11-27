<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Brand;

class AddTomorrowEnergySftpInfo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $te = Brand::where('name', 'Tomorrow Energy')->whereNotNull('client_id')->first();
        DB::table('provider_integrations')->insert([
            [
                'created_at' => now(),
                'updated_at' => now(),
                'brand_id' => $te->id,
                'provider_integration_type_id' => 1,
                'hostname' => 'sftp.tomorrowenergy.com',
                'username' => 'tpvdotcom',
                'password' => '1t7WmtONq%0d',
                'notes' => '/RecordLocator'
            ]
        ]);
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
