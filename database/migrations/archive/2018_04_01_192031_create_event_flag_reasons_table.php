<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class CreateEventFlagReasonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_flag_reasons', function (Blueprint $table) {
            $table->string('id', 36);
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36)->nullable()->foreign()->references('id')->on('brands');
            $table->boolean('fraud_indicator')->default(false);
            $table->string('description', 100);
            $table->boolean('show_to_agents')->default(true);
        });
        DB::table('event_flag_reasons')->insert([
            'id' => '00000000000000000000000000000000',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'deleted_at' => null,
            'brand_id' => null,
            'fraud_indicator' => false,
            'description' => 'No Disposition Chosen by TPV Agent',
            'show_to_agents' => false
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event_flag_reasons');
    }
}
