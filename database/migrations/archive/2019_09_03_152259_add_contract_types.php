<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AddContractTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->string('contract_type', 64);
        });

        $now = Carbon::now();
        DB::table('contract_types')->insert(
            [
                [
                    'id' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'contract_type' => 'Summary Contract',
                ],
                [
                    'id' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'contract_type' => 'Custom Contract',
                ],
                [
                    'id' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'contract_type' => 'Signature Page',
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
        //
    }
}
