<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEngieFieldsToRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'rates', function (Blueprint $table) {
                $table->string('tranche', 64)->nullable();
                $table->string('ratemap', 64)->nullable();
            }
        );

        $when = now();
        DB::table('rate_uoms')->insert(
            [
                ['created_at' => $when, 'updated_at' => $when, 'uom' => 'mwhs'],
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
        Schema::table(
            'rates', function (Blueprint $table) {
                $table->dropColumn('tranche');
                $table->dropColumn('ratemap');
            }
        );
    }
}
