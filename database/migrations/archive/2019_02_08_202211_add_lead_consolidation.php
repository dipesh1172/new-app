<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLeadConsolidation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('leads_whitelist', 'leads');
        Schema::dropIfExists('leads_blacklist');

        Schema::create(
            'lead_type',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('lead_type', 32);
            }
        );

        Schema::table(
            'leads',
            function (Blueprint $table) {
                $table->integer('lead_type_id')->after('channel_id');
            }
        );

        DB::table('lead_type')->insert(
            [
                [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'lead_type' => 'Blacklist'
                ],
                [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'lead_type' => 'Whitelist'
                ],
            ]
        );
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
