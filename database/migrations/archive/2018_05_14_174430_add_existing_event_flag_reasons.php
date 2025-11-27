<?php

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Migrations\Migration;

class AddExistingEventFlagReasons extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('event_flag_reasons')
        ->insert([
            //id, created_at, updated_at, deleted_at, brand_id, fraud_indicator, description, show_to_agents
            ['id' => Uuid::uuid4(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null, 'brand_id' => null, 'fraud_indicator' => 1, 'description' => 'Agent Abusive to Customer', 'show_to_agents' => 1],
            ['id' => Uuid::uuid4(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null, 'brand_id' => null, 'fraud_indicator' => 1, 'description' => 'Agent Acted as Customer','show_to_agents' =>  1],
            ['id' => Uuid::uuid4(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null, 'brand_id' => null, 'fraud_indicator' => 1, 'description' => 'Customer is a child', 'show_to_agents' => 1],
            ['id' => Uuid::uuid4(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null, 'brand_id' => null, 'fraud_indicator' => 0, 'description' => 'Agent heard talking to Customer, Customer denied it', 'show_to_agents' => 1],
            ['id' => Uuid::uuid4(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null, 'brand_id' => null, 'fraud_indicator' => 0, 'description' => 'Agent is rude to TPV', 'show_to_agents' => 1],
            ['id' => Uuid::uuid4(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null, 'brand_id' => null, 'fraud_indicator' => 0, 'description' => 'Agent said something after verification code given that raises concern', 'show_to_agents' => 1],
            ['id' => Uuid::uuid4(), 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'deleted_at' => null, 'brand_id' => null, 'fraud_indicator' => 0, 'description' => 'Prank Call', 'show_to_agents' => 1],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //do nothing
    }
}
