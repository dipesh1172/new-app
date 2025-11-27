<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Carbon\Carbon;

class AddAgentStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'agent_status_types',
            function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('name', 64);
            }
        );

        Schema::table(
            'time_clocks',
            function (Blueprint $table) {
                $table->integer('agent_status_type_id')
                    ->nullable()->after('comment');
            }
        );

        $now = Carbon::now();
        DB::table('agent_status_types')->insert(
            [
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'id' => 1,
                    'name' => 'Time Punch - In',
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'id' => 2,
                    'name' => 'Time Punch - Out',
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'id' => 3,
                    'name' => 'Break',
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'id' => 4,
                    'name' => 'Meeting',
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'id' => 5,
                    'name' => 'Meeting',
                ]
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
        Schema::dropIfExists('agent_status_types');

        Schema::table(
            'time_clocks',
            function (Blueprint $table) {
                $table->dropColumn('agent_status_type_id');
            }
        );
    }
}
