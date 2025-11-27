<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Brand;

class AddGenieCustomerEligibilityApiAlert extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $idt = Brand::where('name', 'IDT Energy')->whereNotNull('client_id')->first();
        if ($idt) {
            DB::table('client_alerts')->insert([
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'title' => 'Genie Customer Eligibility API Check',
                    'description' => 'Performs a check against the Genie provided API for determining if an account is eligible.',
                    'threshold' => 1,
                    'function' => 'genie_customer_eligibility_check',
                    'category_id' => 3,
                    'client_alert_type_id' => 2,
                    'has_threshold' => 0,
                    'brand_id' => $idt->id,
                ]
            ]);
        }

        $residents = Brand::where('name', 'Residents Energy')->whereNotNull('client_id')->first();
        if ($residents) {
            DB::table('client_alerts')->insert([
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'title' => 'Genie Customer Eligibility API Check',
                    'description' => 'Performs a check against the Genie provided API for determining if an account is eligible.',
                    'threshold' => 1,
                    'function' => 'genie_customer_eligibility_check',
                    'category_id' => 3,
                    'client_alert_type_id' => 2,
                    'has_threshold' => 0,
                    'brand_id' => $residents->id,
                ]
            ]);
        }

        $citizens = Brand::where('name', 'Citizens Choice Energy')->whereNotNull('client_id')->first();
        if ($citizens) {
            DB::table('client_alerts')->insert([
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'title' => 'Genie Customer Eligibility API Check',
                    'description' => 'Performs a check against the Genie provided API for determining if an account is eligible.',
                    'threshold' => 1,
                    'function' => 'genie_customer_eligibility_check',
                    'category_id' => 3,
                    'client_alert_type_id' => 2,
                    'has_threshold' => 0,
                    'brand_id' => $citizens->id,
                ]
            ]);
        }

        $townsq = Brand::where('name', 'Town Square')->whereNotNull('client_id')->first();
        if ($townsq) {
            DB::table('client_alerts')->insert([
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'title' => 'Genie Customer Eligibility API Check',
                    'description' => 'Performs a check against the Genie provided API for determining if an account is eligible.',
                    'threshold' => 1,
                    'function' => 'genie_customer_eligibility_check',
                    'category_id' => 3,
                    'client_alert_type_id' => 2,
                    'has_threshold' => 0,
                    'brand_id' => $townsq->id,
                ]
            ]);
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
