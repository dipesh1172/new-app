<?php

use App\Models\Brand;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Ramsey\Uuid\Uuid;

class AddPendingAndAbandonedDispositionsForAllBrands extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $brands = DB::table('brands')
            ->whereNotNull('client_id')
            ->get();

        foreach ($brands as $brand) {
            $abandoned_exists = DB::table('dispositions')
                ->where('brand_id', $brand->id)
                ->where('reason', 'Abandoned')
                ->first();
            if (!$abandoned_exists) {
                $now = Carbon::now();
                DB::table('dispositions')->insert(
                    [
                        [
                            'id' => Uuid::uuid4(),
                            'created_at' => $now,
                            'updated_at' => $now,
                            'disposition_category_id' => 6,
                            'brand_id' => $brand->id,
                            'brand_label' => '60002',
                            'reason' => 'Abandoned',
                            'description' => 'EzTPV was abandoned before completion',
                            'resolution' => null,
                            'fraud_indicator' => 0
                        ],
                    ]
                );
            }
            
            $pending_exists = DB::table('dispositions')
                ->where('brand_id', $brand->id)
                ->where('reason', 'Pending')
                ->first();
            if (!$pending_exists) {
                $now = Carbon::now();
                DB::table('dispositions')->insert(
                    [
                        [
                            'id' => Uuid::uuid4(),
                            'created_at' => $now,
                            'updated_at' => $now,
                            'disposition_category_id' => 5,
                            'brand_id' => $brand->id,
                            'brand_label' => '60001',
                            'reason' => 'Pending',
                            'description' => 'EzTPV is pending completion',
                            'resolution' => null,
                            'fraud_indicator' => 0
                        ],
                    ]
                );
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
        //
    }
}
