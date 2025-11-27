<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Brand;

class AddIndraPhoneApiAlert extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $indra = Brand::where('name', 'Indra Energy')->whereNotNull('client_id')->first();
        DB::table('client_alerts')->insert([
            [
                'created_at' => now(),
                'updated_at' => now(),
                'title' => 'Active/Do Not Call API Check',
                'description' => 'Performs a check against the Indra provided API for confirming phone numbers.',
                'threshold' => 1,
                'function' => 'indra_active_dnc_api_check',
                'category_id' => 3,
                'client_alert_type_id' => 2,
                'has_threshold' => 0,
                'brand_id' => $indra->id,
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
