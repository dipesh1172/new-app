<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class CreateUtilityAccountNumberTypesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'utility_account_number_types', function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('desc');
            }
        );

        $now = Carbon::now();
        DB::table('utility_account_number_types')->insert(
            [
                ['id' => 1, 'desc' => 'Account Number 1', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'desc' => 'Account Number 2', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'desc' => 'Name Key', 'created_at' => $now, 'updated_at' => $now],
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('utility_account_number_types');
    }
}
